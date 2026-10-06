<?php

namespace App\Modules\Finance\Actions;

use App\Integrations\Payments\PaymentGateways;
use App\Integrations\Payments\ProviderStatus;
use App\Integrations\Payments\Verification;
use App\Modules\Accounts\Models\User;
use App\Modules\Finance\Enums\PaymentState;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\ReconciliationCase;
use App\Modules\Orders\Exceptions\OrderForbidden;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * `Finance\RefreshPaymentStatus` : vérification serveur auprès du prestataire de LA TENTATIVE (limitée : une toutes les 10 s).
 * C'est le rapprochement quand une notification est perdue ou qu'un appel de création a dépassé son délai (même demande rejouée, jamais une
 * nouvelle tentative). Un navigateur ne peut rien confirmer : il demande seulement au SERVEUR d'interroger le prestataire.
 */
final class RefreshPaymentStatus
{
    public const MIN_INTERVAL_SECONDS = 10;

    public function __construct(private PaymentGateways $gateways, private ConfirmPayment $confirm, private CheckoutRecorder $recorder, private PaymentVerifier $verifier) {}

    /** @return array{0: ?Payment, 1: bool} [tentative, vrai si la limite d'actualisation a été atteinte] */
    public function __invoke(User $client, string $reference): array
    {
        $order = Order::query()->where('reference', $reference)->where('client_id', $client->getKey())->first();
        if ($order === null) {
            throw new OrderForbidden;
        }
        $payment = Payment::query()->where('order_id', $order->getKey())->orderByDesc('id')->first();
        if ($payment === null || ! $payment->state->isOpen() || ! $payment->isGenius()) {      // le suivi d'une tentative ne dépend pas de l'ouverture des nouveaux paiements
            return [$payment, false];
        }
        if ($payment->last_checked_at !== null && $payment->last_checked_at->gt(now()->subSeconds(self::MIN_INTERVAL_SECONDS))) {
            return [$payment, true];
        }

        return [$this->forPayment($payment, $order), false];
    }

    /** Vérification d'une tentative OUVERTE (aussi appelée par la tâche de rapprochement). */
    public function forPayment(Payment $payment, ?Order $order = null): Payment
    {
        $order ??= Order::query()->whereKey($payment->order_id)->firstOrFail();
        if (! $payment->isGenius()) {
            return $payment;                         // ancienne tentative du simulateur : jamais interrogée ni confirmée
        }
        DB::table('payments')->where('id', $payment->getKey())->update(['last_checked_at' => now()]);
        $provider = $this->gateways->forEnvironment($payment->environment);   // environnement ENREGISTRÉ de la tentative

        // Tentative créée mais référence du prestataire inconnue (délai dépassé à la création) → on REJOUE la même création (idempotente).
        if ($payment->isGenius() && $payment->provider_transaction_reference === null) {
            $result = $this->recorder->run($payment);
            $payment = $payment->fresh();
            if ($result !== 'pending' || $payment->provider_transaction_reference === null) {
                return $payment;
            }
        }

        $v = $provider->verify($payment->verificationReference());
        match ($v->status) {
            ProviderStatus::Succeeded => $this->confirmIfConsistent($payment, $order, $v),
            ProviderStatus::Failed => $this->setState($payment, PaymentState::Failed, 'provider_declined'),
            ProviderStatus::Indeterminate => $this->setState($payment, PaymentState::Unknown),
            ProviderStatus::Refunded => $this->flag($order, $payment, 'refunded_by_provider', $v),
            // Référence inconnue du prestataire après 2 minutes : l'appel de création n'a jamais abouti → tentative échouée.
            ProviderStatus::NotFound => $payment->created_at->lt(now()->subMinutes(2)) ? $this->setState($payment, PaymentState::Failed, 'checkout_incomplete') : null,
            ProviderStatus::Pending, ProviderStatus::Other => null,
        };

        return $payment->fresh();
    }

    private function confirmIfConsistent(Payment $payment, Order $order, Verification $v): void
    {
        $reason = $this->verifier->inconsistency($payment, $order, $v);
        if ($reason === null) {
            ($this->confirm)($payment->getKey());

            return;
        }
        $this->flag($order, $payment, $reason, $v);        // « à vérifier » : aucune confirmation, aucun démarrage
    }

    private function flag(Order $order, Payment $payment, string $reason, Verification $v): void
    {
        ReconciliationCase::query()->firstOrCreate(
            ['order_id' => $order->getKey(), 'payment_id' => $payment->getKey(), 'reason' => $reason],
            ['details' => ['verified_status' => $v->status->value, 'verified_amount' => $v->amountXof, 'environment' => $payment->environment]],
        );
    }

    private function setState(Payment $payment, PaymentState $to, ?string $code = null): void
    {
        Payment::query()->whereKey($payment->getKey())->whereIn('state', ['created', 'pending', 'unknown'])->update([
            'state' => $to->value, 'failure_code' => $code, 'failed_at' => $to === PaymentState::Failed ? now() : null,
            'row_version' => DB::raw('row_version + 1'), 'updated_at' => now(),
        ]);
    }
}
