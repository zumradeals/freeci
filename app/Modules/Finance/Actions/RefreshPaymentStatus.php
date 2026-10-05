<?php

namespace App\Modules\Finance\Actions;

use App\Integrations\Payments\PaymentProvider;
use App\Integrations\Payments\ProviderStatus;
use App\Integrations\Payments\Verification;
use App\Modules\Accounts\Models\User;
use App\Modules\Finance\Enums\PaymentState;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\SandboxGate;
use App\Modules\Orders\Exceptions\OrderForbidden;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * `Finance\RefreshPaymentStatus` : vérification serveur auprès du prestataire (limitée : une toutes les 10 s).
 * C'est le rapprochement quand une notification est perdue. Un navigateur ne peut rien confirmer : il demande seulement
 * au SERVEUR d'interroger le prestataire.
 */
final class RefreshPaymentStatus
{
    public const MIN_INTERVAL_SECONDS = 10;

    public function __construct(private PaymentProvider $provider, private ConfirmPayment $confirm, private SandboxGate $gate) {}

    /** @return array{0: ?Payment, 1: bool} [tentative, vrai si la limite d'actualisation a été atteinte] */
    public function __invoke(User $client, string $reference): array
    {
        $order = Order::query()->where('reference', $reference)->where('client_id', $client->getKey())->first();
        if ($order === null) {
            throw new OrderForbidden;
        }
        $payment = Payment::query()->where('order_id', $order->getKey())->orderByDesc('id')->first();
        if ($payment === null || ! $payment->state->isOpen() || ! $this->gate->allows($order)) {
            return [$payment, false];
        }
        if ($payment->last_checked_at !== null && $payment->last_checked_at->gt(now()->subSeconds(self::MIN_INTERVAL_SECONDS))) {
            return [$payment, true];
        }
        DB::table('payments')->where('id', $payment->getKey())->update(['last_checked_at' => now()]);

        $v = $this->provider->verify($payment->provider_reference);
        match ($v->status) {
            ProviderStatus::Succeeded => $this->confirmIfConsistent($payment, $order, $v),
            ProviderStatus::Failed => $this->setState($payment, PaymentState::Failed, 'provider_declined'),
            ProviderStatus::Indeterminate => $this->setState($payment, PaymentState::Unknown),
            // Référence inconnue du prestataire après 2 minutes : l'appel de création n'a jamais abouti → tentative échouée.
            ProviderStatus::NotFound => $payment->created_at->lt(now()->subMinutes(2)) ? $this->setState($payment, PaymentState::Failed, 'checkout_incomplete') : null,
            ProviderStatus::Pending => null,
        };

        return [$payment->fresh(), false];
    }

    private function confirmIfConsistent(Payment $payment, Order $order, Verification $v): void
    {
        if ($v->amountXof === $payment->amount_xof && $v->currency === $payment->currency && $v->orderReference === $order->reference) {
            ($this->confirm)($payment->getKey());
        }
    }

    private function setState(Payment $payment, PaymentState $to, ?string $code = null): void
    {
        Payment::query()->whereKey($payment->getKey())->whereIn('state', ['created', 'pending', 'unknown'])->update([
            'state' => $to->value, 'failure_code' => $code, 'failed_at' => $to === PaymentState::Failed ? now() : null,
            'row_version' => DB::raw('row_version + 1'), 'updated_at' => now(),
        ]);
    }
}
