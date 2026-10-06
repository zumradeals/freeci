<?php

namespace App\Modules\Finance\Actions;

use App\Integrations\Payments\PaymentGateways;
use App\Integrations\Payments\PaymentMode;
use App\Modules\Accounts\Models\User;
use App\Modules\Finance\Enums\PaymentState;
use App\Modules\Finance\Exceptions\PaymentAlreadyConfirmed;
use App\Modules\Finance\Exceptions\PaymentInProgress;
use App\Modules\Finance\Exceptions\PaymentNotAvailable;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\PaymentGate;
use App\Modules\Orders\Actions\ExpireOverdueOrders;
use App\Modules\Orders\Enums\OrderState;
use App\Modules\Orders\Exceptions\InvalidTransition;
use App\Modules\Orders\Exceptions\OrderExpired;
use App\Modules\Orders\Exceptions\OrderForbidden;
use App\Modules\Orders\Models\Order;
use App\Shared\CommandReceipts;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

/**
 * `Finance\InitiatePayment` : le client de la commande ouvre UNE tentative de paiement Genius Pay, dans l'environnement du mode actif.
 * Le montant est lu dans l'accord (jamais dans la requête). Refusé si les nouveaux paiements ne sont pas ouverts, si l'environnement de la commande
 * ne correspond pas au mode actif, si une tentative est déjà ouverte (en cours ou incertaine) ou si le paiement est déjà confirmé.
 */
final class InitiatePayment
{
    public function __construct(private PaymentGateways $gateways, private PaymentGate $gate, private ExpireOverdueOrders $expire, private CheckoutRecorder $recorder) {}

    /** @return array{0: Payment, 1: bool} [tentative, vrai si répétition de la même opération] */
    public function __invoke(User $client, string $reference, string $operationKey): array
    {
        $order = Order::query()->where('reference', $reference)->where('client_id', $client->getKey())->first();
        if ($order === null) {
            throw new OrderForbidden;
        }
        $this->expire->forOrder($order->getKey());
        if (Order::find($order->getKey())->state === OrderState::Expired) {
            throw new OrderExpired;
        }

        // Live : le compte marchand de l'API doit être celui déclaré AVANT toute création (appel hors transaction). Sandbox : non bloquant.
        if (PaymentMode::isLive() && PaymentMode::creationOpen() && $this->gateways->active()->merchantStatus() !== 'ok') {
            throw new PaymentNotAvailable;
        }

        [$orderId, $replayed] = CommandReceipts::once($client->getKey(), 'finance.initiate_payment', $operationKey, ['reference' => $reference], function () use ($order, $client) {
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();   // ordre de verrouillage : commande, puis opérations
            if ($locked->client_id !== $client->getKey()) {
                throw new OrderForbidden;
            }
            $provider = $this->gateways->active();
            // Porte : paiements ouverts (mode, autorisation, configuration) et environnement de la commande égal au mode actif.
            if (! $this->gate->allows($locked)) {
                throw new PaymentNotAvailable;
            }
            if ($locked->state !== OrderState::AwaitingPayment || $locked->started_at !== null) {
                throw new InvalidTransition;
            }
            if ($locked->payments()->where('state', PaymentState::Confirmed->value)->exists()) {
                throw new PaymentAlreadyConfirmed;
            }
            if ($locked->payments()->whereIn('state', [PaymentState::Created->value, PaymentState::Pending->value, PaymentState::Unknown->value])->exists()) {
                throw new PaymentInProgress;
            }

            try {
                $payment = Payment::create([
                    'order_id' => $locked->getKey(),
                    'amount_xof' => $locked->agreement->price_xof,          // lu dans l'accord figé
                    'currency' => 'XOF',
                    'provider' => $provider->name(),
                    'environment' => $provider->environment(),                 // conservé pour cette tentative : jamais réinterprété
                    'is_simulated' => $provider->environment() !== 'live',      // un paiement du bac à sable n'est JAMAIS de l'argent réel
                    // Référence STABLE de la tentative, générée et ENREGISTRÉE avant tout appel au prestataire (aussi sa clé d'idempotence).
                    'provider_reference' => ($provider->environment() === 'live' ? 'GP-' : 'GPS-').strtoupper(Str::random(14)),
                    'state' => PaymentState::Created,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw new PaymentInProgress;       // rempart : index unique « une seule tentative ouverte par commande »
            }
            $locked->events()->create(['type' => 'payment_started', 'actor_id' => $client->getKey(), 'note' => ($provider->environment() === 'sandbox' ? 'Paiement Genius Pay (mode test, aucun argent réel) démarré (' : 'Paiement Genius Pay démarré (').$payment->provider_reference.').']);

            return $locked->getKey();
        });

        $payment = Payment::query()->where('order_id', $orderId)->latest('id')->firstOrFail();
        if ($replayed || $payment->state !== PaymentState::Created) {
            return [$payment, $replayed];
        }

        // Appel au prestataire HORS de la transaction : `created -> pending`, ou échec définitif, ou résultat incertain (la tentative reste ouverte).
        $this->recorder->run($payment);

        return [$payment->fresh(), false];
    }
}
