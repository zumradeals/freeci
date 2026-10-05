<?php

namespace App\Modules\Finance\Actions;

use App\Integrations\Payments\PaymentProvider;
use App\Integrations\Payments\ProviderEvent;
use App\Integrations\Payments\ProviderStatus;
use App\Modules\Finance\Enums\PaymentState;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\ReconciliationCase;
use App\Modules\Finance\SandboxGate;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Traite une notification déjà AUTHENTIFIÉE (signature vérifiée par l'adaptateur). Dédoublonnée par identifiant d'événement,
 * non régressive (un état final ne change plus), et la confirmation n'est jamais crue sur parole : elle est revérifiée.
 */
final class ProcessProviderEvent
{
    public function __construct(private PaymentProvider $provider, private ConfirmPayment $confirm, private SandboxGate $gate) {}

    /** @return string 'applied' | 'duplicate' | 'ignored' | 'rejected' | 'reconciliation' */
    public function __invoke(ProviderEvent $event): string
    {
        $inserted = DB::table('payment_events')->insertOrIgnore([
            'provider' => $event->provider, 'provider_event_id' => $event->eventId, 'provider_reference' => $event->reference,
            'type' => $event->status->value, 'payload' => json_encode($event->payload), 'received_at' => now(),
        ]);
        if ($inserted === 0) {
            // Événement reçu plusieurs fois : compté, jamais ré-appliqué.
            DB::table('payment_events')->where(['provider' => $event->provider, 'provider_event_id' => $event->eventId])->increment('duplicate_count');

            return 'duplicate';
        }

        $outcome = $this->apply($event);
        DB::table('payment_events')->where(['provider' => $event->provider, 'provider_event_id' => $event->eventId])
            ->update(['outcome' => $outcome, 'payment_id' => Payment::query()->where('provider_reference', $event->reference)->value('id')]);

        return $outcome;
    }

    private function apply(ProviderEvent $event): string
    {
        $payment = Payment::query()->where('provider_reference', $event->reference)->first();
        if ($payment === null) {
            return 'ignored';                              // référence inconnue
        }
        $order = Order::query()->whereKey($payment->order_id)->firstOrFail();
        if (! $this->gate->allows($order)) {
            return 'rejected';                             // simulateur désactivé ou commande non autorisée
        }

        return match ($event->status) {
            ProviderStatus::Pending => $this->transition($payment, PaymentState::Pending),
            ProviderStatus::Indeterminate => $this->transition($payment, PaymentState::Unknown),
            ProviderStatus::Failed => $this->fail($payment),
            ProviderStatus::Succeeded => $this->succeed($payment, $order),
            ProviderStatus::NotFound => 'ignored',
        };
    }

    private function transition(Payment $payment, PaymentState $to): string
    {
        $n = Payment::query()->whereKey($payment->getKey())->where('state', $payment->state->value)->whereIn('state', ['created', 'pending', 'unknown'])
            ->when($to === PaymentState::Pending, fn ($q) => $q->where('state', 'created'))
            ->update(['state' => $to->value, 'pending_at' => $to === PaymentState::Pending ? now() : $payment->pending_at, 'row_version' => DB::raw('row_version + 1'), 'updated_at' => now()]);

        return $n === 1 ? 'applied' : 'ignored';          // régression ignorée (ex. « en attente » après « confirmé »)
    }

    private function fail(Payment $payment): string
    {
        $n = Payment::query()->whereKey($payment->getKey())->whereIn('state', ['created', 'pending', 'unknown'])
            ->update(['state' => PaymentState::Failed->value, 'failed_at' => now(), 'failure_code' => 'provider_declined', 'row_version' => DB::raw('row_version + 1'), 'updated_at' => now()]);
        if ($n === 1) {
            Order::query()->whereKey($payment->order_id)->first()?->events()->create(['type' => 'payment_failed', 'actor_id' => null, 'note' => 'Paiement simulé non abouti ('.$payment->provider_reference.').']);

            return 'applied';
        }

        return 'ignored';                                  // un paiement confirmé ne devient jamais « échoué »
    }

    private function succeed(Payment $payment, Order $order): string
    {
        // La notification n'est jamais crue sur parole : le statut, le montant, la devise et la commande sont revérifiés.
        $v = $this->provider->verify($payment->provider_reference);
        if ($v->status !== ProviderStatus::Succeeded || $v->amountXof !== $payment->amount_xof || $v->currency !== $payment->currency || $v->orderReference !== $order->reference) {
            ReconciliationCase::query()->create([
                'order_id' => $order->getKey(), 'payment_id' => $payment->getKey(), 'reason' => 'verification_mismatch',
                'details' => ['verified_status' => $v->status->value, 'verified_amount' => $v->amountXof],
            ]);

            return 'rejected';
        }

        return match (($this->confirm)($payment->getKey())) {
            'applied' => 'applied',
            'reconciliation' => 'reconciliation',
            default => 'ignored',          // déjà confirmé : aucun second effet
        };
    }
}
