<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Enums\PaymentState;
use App\Modules\Finance\Models\LedgerBatch;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\ReconciliationCase;
use App\Modules\Missions\Actions\MissionLifecycle;
use App\Modules\Orders\Actions\StartOrderIfReady;
use App\Modules\Orders\Enums\OrderState;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * `Finance\ConfirmPayment` : appelée UNIQUEMENT après vérification serveur auprès du prestataire (jamais par une requête
 * de navigateur). Une seule confirmation, un seul lot de registre, un seul démarrage — quel que soit le nombre d'appels.
 */
final class ConfirmPayment
{
    public function __construct(private StartOrderIfReady $start) {}

    /** @return string 'applied' | 'already' | 'reconciliation' */
    public function __invoke(string $paymentId): string
    {
        return DB::transaction(function () use ($paymentId) {
            $orderId = Payment::query()->whereKey($paymentId)->value('order_id');
            // Ordre de verrouillage fixe : commande, puis opérations (docs/02 §8.2).
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->firstOrFail();
            $payment = Payment::query()->whereKey($paymentId)->lockForUpdate()->firstOrFail();

            if ($payment->state === PaymentState::Confirmed) {
                return 'already';
            }
            if (! $payment->state->isOpen()) {
                // Confirmation reçue après un échec ou une expiration : jamais de réouverture automatique.
                $this->reconcile($order, $payment, 'confirmed_after_'.$payment->state->value);

                return 'reconciliation';
            }

            $updated = Payment::query()->whereKey($paymentId)->whereIn('state', ['created', 'pending', 'unknown'])
                ->update(['state' => PaymentState::Confirmed->value, 'confirmed_at' => now(), 'last_checked_at' => now(), 'row_version' => DB::raw('row_version + 1'), 'updated_at' => now()]);
            if ($updated !== 1) {
                return 'already';
            }
            $payment->refresh();

            // État financier : un seul lot équilibré par paiement (clé d'événement unique).
            $batch = LedgerBatch::query()->firstOrCreate(
                ['event_key' => 'payment_confirmed:'.$payment->getKey()],
                ['order_id' => $order->getKey(), 'payment_id' => $payment->getKey(), 'kind' => 'payment_confirmed', 'is_simulated' => $payment->is_simulated, 'occurred_at' => now()],
            );
            if ($batch->wasRecentlyCreated) {
                $batch->lines()->createMany([
                    ['account' => 'external_payer_simulated', 'amount_xof' => -$payment->amount_xof],
                    ['account' => 'escrow_simulated', 'amount_xof' => $payment->amount_xof],
                ]);
            }

            if ($order->state !== OrderState::AwaitingPayment && $order->state !== OrderState::AwaitingBrief) {
                // Argent reçu sur une commande déjà fermée : on trace, le support examinera ; rien ne démarre.
                $this->reconcile($order, $payment, 'paid_after_order_'.$order->state->value);

                return 'reconciliation';
            }

            $order->events()->create(['type' => 'payment_confirmed', 'actor_id' => null, 'note' => ($payment->environment === 'sandbox' ? 'Paiement Genius Pay (bac à sable, aucun argent réel) confirmé, vérifié côté serveur (' : 'Paiement simulé confirmé (').$payment->provider_reference.').']);
            if ($order->mission_id !== null) {
                app(MissionLifecycle::class)->onPaymentConfirmed($order);        // la mission n'est attribuée QU'ICI, paiement vérifié côté serveur
            }
            ($this->start)($order);

            return 'applied';
        });
    }

    private function reconcile(Order $order, Payment $payment, string $reason): void
    {
        ReconciliationCase::query()->firstOrCreate(
            ['order_id' => $order->getKey(), 'payment_id' => $payment->getKey(), 'reason' => $reason],
            ['details' => ['payment_state' => $payment->state->value, 'order_state' => $order->state->value, 'environment' => $payment->environment, 'amount_xof' => (int) $payment->amount_xof]],
        );
    }
}
