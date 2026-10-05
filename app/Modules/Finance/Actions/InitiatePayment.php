<?php

namespace App\Modules\Finance\Actions;

use App\Integrations\Payments\CheckoutRequest;
use App\Integrations\Payments\PaymentProvider;
use App\Modules\Accounts\Models\User;
use App\Modules\Finance\Enums\PaymentState;
use App\Modules\Finance\Exceptions\PaymentAlreadyConfirmed;
use App\Modules\Finance\Exceptions\PaymentInProgress;
use App\Modules\Finance\Exceptions\PaymentNotAvailable;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\SandboxGate;
use App\Modules\Orders\Actions\ExpireOverdueOrders;
use App\Modules\Orders\Enums\OrderState;
use App\Modules\Orders\Exceptions\InvalidTransition;
use App\Modules\Orders\Exceptions\OrderExpired;
use App\Modules\Orders\Exceptions\OrderForbidden;
use App\Modules\Orders\Models\Order;
use App\Shared\CommandReceipts;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * `Finance\InitiatePayment` : le client de la commande ouvre UNE tentative de paiement simulé.
 * Le montant est lu dans l'accord (jamais dans la requête). Refusé si le simulateur n'est pas autorisé pour cette commande,
 * si une tentative est déjà ouverte (en cours ou incertaine) ou si le paiement est déjà confirmé.
 */
final class InitiatePayment
{
    public function __construct(private PaymentProvider $provider, private SandboxGate $gate, private ExpireOverdueOrders $expire) {}

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

        [$orderId, $replayed] = CommandReceipts::once($client->getKey(), 'finance.initiate_payment', $operationKey, ['reference' => $reference], function () use ($order, $client) {
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();   // ordre de verrouillage : commande, puis opérations
            if ($locked->client_id !== $client->getKey()) {
                throw new OrderForbidden;
            }
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
                    'provider' => $this->provider->name(),
                    'is_simulated' => true,
                    // Référence générée et ENREGISTRÉE avant tout appel au prestataire.
                    'provider_reference' => 'SBX-'.strtoupper(Str::random(14)),
                    'state' => PaymentState::Created,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw new PaymentInProgress;       // rempart : index unique « une seule tentative ouverte par commande »
            }
            $locked->events()->create(['type' => 'payment_started', 'actor_id' => $client->getKey(), 'note' => 'Paiement simulé démarré ('.$payment->provider_reference.').']);

            return $locked->getKey();
        });

        $payment = Payment::query()->where('order_id', $orderId)->latest('id')->firstOrFail();
        if ($replayed || $payment->state !== PaymentState::Created) {
            return [$payment, $replayed];
        }

        // Appel au prestataire HORS de la transaction : `created -> pending` par mise à jour conditionnelle.
        try {
            $this->provider->createCheckout(new CheckoutRequest($payment->getKey(), $payment->provider_reference, $reference, $payment->amount_xof, $payment->currency));
            DB::table('payments')->where('id', $payment->getKey())->where('state', PaymentState::Created->value)
                ->update(['state' => PaymentState::Pending->value, 'pending_at' => now(), 'row_version' => DB::raw('row_version + 1'), 'updated_at' => now()]);
        } catch (\Throwable $e) {
            // Résultat inconnu : l'opération reste « créée » ; le rapprochement tranchera (RefreshPaymentStatus).
            report($e);
        }

        return [$payment->fresh(), false];
    }
}
