<?php

namespace App\Modules\Finance\Actions;

use App\Integrations\Payments\CheckoutRequest;
use App\Integrations\Payments\PaymentProviders;
use App\Integrations\Payments\ProviderRejected;
use App\Modules\Finance\Enums\PaymentState;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\ReconciliationCase;
use App\Modules\Orders\Models\Order;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Appel de création (ou de REPRISE) du checkout et enregistrement du résultat. La création est idempotente chez le prestataire (même référence de
 * tentative, même clé) : après un délai dépassé on rejoue la MÊME demande, jamais une nouvelle tentative.
 */
final class CheckoutRecorder
{
    public function __construct(private PaymentProviders $providers) {}

    /** @return 'pending'|'failed'|'uncertain' */
    public function run(Payment $payment): string
    {
        $order = Order::query()->whereKey($payment->order_id)->first();
        if ($order === null) {
            return 'uncertain';
        }
        $provider = $this->providers->named($payment->provider);
        $desc = 'Commande '.$order->reference.' (FreeCI'.($payment->environment === 'sandbox' ? ', bac à sable' : '').')';
        $request = new CheckoutRequest(
            $payment->getKey(), $payment->provider_reference, $order->reference, (int) $payment->amount_xof, $payment->currency, $desc,
            route('orders.payment.return', $order->reference), route('orders.payment.return', $order->reference),
        );
        try {
            $r = $provider->createCheckout($request);
        } catch (ProviderRejected $e) {
            // Refus définitif : aucune transaction n'existe chez le prestataire. La tentative est close ; une nouvelle pourra être ouverte.
            $n = DB::table('payments')->where('id', $payment->getKey())->whereIn('state', ['created'])->update([
                'state' => PaymentState::Failed->value, 'failed_at' => now(), 'failure_code' => mb_substr($e->getMessage(), 0, 40), 'row_version' => DB::raw('row_version + 1'), 'updated_at' => now(),
            ]);
            if ($n === 1) {
                $order->events()->create(['type' => 'payment_failed', 'actor_id' => null, 'note' => 'Paiement non abouti : le prestataire a refusé la création ('.$payment->provider_reference.').']);
                if ($e->getMessage() === 'environment_mismatch' || $e->getMessage() === 'amount_mismatch') {
                    ReconciliationCase::query()->create(['order_id' => $order->getKey(), 'payment_id' => $payment->getKey(), 'reason' => $e->getMessage(), 'details' => ['phase' => 'creation']]);
                }
            }

            return 'failed';
        } catch (\Throwable $e) {
            report($e);          // résultat inconnu : la tentative reste « créée » (ouverte) ; le rapprochement rejoue la même demande

            return 'uncertain';
        }

        DB::table('payments')->where('id', $payment->getKey())->where('state', PaymentState::Created->value)->update([
            'state' => PaymentState::Pending->value, 'pending_at' => now(), 'row_version' => DB::raw('row_version + 1'), 'updated_at' => now(),
        ]);
        try {
            DB::table('payments')->where('id', $payment->getKey())->update(array_filter([
                'provider_transaction_reference' => $r->transactionReference, 'provider_transaction_id' => $r->transactionId, 'checkout_url' => $r->redirectUrl,
                'provider_expires_at' => $r->expiresAt === null ? null : Carbon::parse($r->expiresAt), 'binding_verified_at' => $r->bindingVerified ? now() : null, 'updated_at' => now(),
            ], fn ($v) => $v !== null));
        } catch (UniqueConstraintViolationException) {
            // Le prestataire a renvoyé pour cette tentative une référence DÉJÀ rattachée à une autre : incohérence, la tentative est signalée et close.
            DB::table('payments')->where('id', $payment->getKey())->update(['state' => PaymentState::Failed->value, 'failed_at' => now(), 'failure_code' => 'reference_conflict', 'row_version' => DB::raw('row_version + 1'), 'updated_at' => now()]);
            ReconciliationCase::query()->firstOrCreate(['order_id' => $order->getKey(), 'payment_id' => $payment->getKey(), 'reason' => 'reference_mismatch'], ['details' => ['phase' => 'creation']]);

            return 'failed';
        }

        return 'pending';
    }
}
