<?php

namespace App\Modules\Finance\Actions;

use App\Integrations\Payments\GeniusPayProvider;
use App\Integrations\Payments\PaymentGateways;
use App\Integrations\Payments\ProviderEvent;
use App\Integrations\Payments\ProviderStatus;
use App\Modules\Finance\Enums\PaymentState;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\ReconciliationCase;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Traite une notification déjà AUTHENTIFIÉE (signature vérifiée par l'adaptateur). Deux temps :
 *  1. `record` : l'événement est ENREGISTRÉ durablement (dédoublonné par identifiant) — rien d'autre ;
 *  2. `process` : traitement (asynchrone) — non régressif, et la confirmation n'est JAMAIS crue sur parole :
 *     elle est revérifiée auprès du prestataire (statut, montant, devise, référence, environnement) avant tout effet.
 * Résultats : applied | duplicate | ignored | rejected | reconciliation | review (information manquante ou incertaine : à revérifier, aucun démarrage).
 */
final class ProcessProviderEvent
{
    public function __construct(private PaymentGateways $gateways, private ConfirmPayment $confirm, private PaymentVerifier $verifier, private CheckoutRecorder $recorder) {}

    /** Enregistrement + traitement immédiat. */
    public function __invoke(ProviderEvent $event): string
    {
        [$id, $duplicate] = $this->record($event, 'processed');

        return $duplicate ? 'duplicate' : $this->process((int) $id);
    }

    /** @return array{0: ?int, 1: bool} [identifiant de la ligne (nouvelle), vrai si l'événement était déjà connu] */
    public function record(ProviderEvent $event, string $processing = 'received'): array
    {
        $inserted = DB::table('payment_events')->insertOrIgnore([
            'provider' => $event->provider, 'provider_event_id' => $event->eventId, 'provider_reference' => $event->reference,
            'type' => $event->status->value, 'payload' => json_encode($event->payload), 'received_at' => now(), 'processing' => $processing === 'processed' ? 'received' : $processing,
            'environment' => $event->payload['environment'] ?? null, 'signature_timestamp' => $event->payload['signature_timestamp'] ?? null,
        ]);
        $row = DB::table('payment_events')->where(['provider' => $event->provider, 'provider_event_id' => $event->eventId])->first(['id']);
        if ($inserted === 0) {
            // Événement reçu plusieurs fois (reprises du prestataire comprises) : compté, jamais ré-appliqué.
            DB::table('payment_events')->where('id', $row->id)->increment('duplicate_count');

            return [null, true];
        }

        return [(int) $row->id, false];
    }

    /** Traite une ligne enregistrée. Idempotent : une ligne déjà traitée n'est jamais ré-appliquée. */
    public function process(int $rowId): string
    {
        $row = DB::table('payment_events')->where('id', $rowId)->first();
        if ($row === null || $row->processing === 'processed') {
            return 'duplicate';
        }
        $payload = json_decode((string) $row->payload, true) ?: [];
        $event = new ProviderEvent($row->provider, $row->provider_event_id, (string) $row->provider_reference, ProviderStatus::from($row->type), $payload);
        $payment = $this->find($event);
        DB::table('payment_events')->where('id', $rowId)->increment('attempts');

        try {
            $outcome = $this->apply($event, $payment);
        } catch (\Throwable $e) {
            report($e);
            $outcome = 'review';
        }

        $review = $outcome === 'review';
        DB::table('payment_events')->where('id', $rowId)->update([
            'outcome' => $review ? null : $outcome, 'payment_id' => $payment?->getKey(), 'processing' => $review ? 'needs_review' : 'processed', 'processed_at' => now(),
            'review_reason' => $review ? mb_substr((string) ($this->reviewReason ?? 'verification_incomplete'), 0, 60) : null,
        ]);
        $this->reviewReason = null;

        return $outcome;
    }

    private ?string $reviewReason = null;

    private function find(ProviderEvent $event): ?Payment
    {
        $p = Payment::query()->where('provider', $event->provider)->where('provider_transaction_reference', $event->reference)->first();
        if ($p !== null) {
            return $p;
        }
        // Notification reçue avant que la réponse de création ait été enregistrée : retrouvée par NOTRE référence de tentative (métadonnée envoyée).
        $attempt = $event->payload['transaction']['attempt'] ?? null;

        return is_string($attempt) ? Payment::query()->where('provider', $event->provider)->where('provider_reference', $attempt)->first() : null;
    }

    private function apply(ProviderEvent $event, ?Payment $payment): string
    {
        if ($payment === null) {
            return 'ignored';                              // référence inconnue
        }
        $order = Order::query()->whereKey($payment->order_id)->firstOrFail();
        // Le suivi d'une tentative EXISTANTE ne dépend ni du mode courant ni de l'ouverture des nouveaux paiements : seul compte l'environnement ENREGISTRÉ.
        if (! $payment->isGenius()) {
            return 'ignored';                              // ancienne tentative du simulateur retiré
        }
        $early = $this->checkGenius($event, $payment, $order);
        if ($early !== null) {
            return $early;
        }

        return match ($event->status) {
            ProviderStatus::Pending => $this->transition($payment, PaymentState::Pending),
            ProviderStatus::Indeterminate => $this->transition($payment, PaymentState::Unknown),
            ProviderStatus::Failed => $this->fail($payment, $order, (string) ($event->payload['event'] ?? '') === 'payment.cancelled' ? 'provider_cancelled' : 'provider_declined'),
            ProviderStatus::Succeeded => $this->succeed($payment, $order),
            ProviderStatus::Refunded => $this->refunded($payment, $order),
            ProviderStatus::NotFound, ProviderStatus::Other => 'ignored',
        };
    }

    /** Contrôles : environnement, compte marchand, rattachement de la référence. @return string|null issue finale si un contrôle échoue */
    private function checkGenius(ProviderEvent $event, Payment $payment, Order $order): ?string
    {
        if (($event->payload['environment'] ?? null) !== $payment->environment) {
            $this->flag($order, $payment, 'environment_mismatch');

            return 'rejected';
        }
        $provider = $this->gateways->forEnvironment($payment->environment);
        if ($provider instanceof GeniusPayProvider && $provider->merchantStatus() === 'mismatch') {
            $this->flag($order, $payment, 'merchant_mismatch');          // le compte de l'API n'est pas celui déclaré pour cet environnement

            return 'rejected';
        }
        $merchant = $provider instanceof GeniusPayProvider ? $provider->merchantId() : null;
        $eventMerchant = $event->payload['merchant_id'] ?? null;
        if ($merchant === null || $eventMerchant === null) {
            $this->reviewReason = $merchant === null ? 'merchant_unverifiable' : 'merchant_missing';

            return 'review';                               // information manquante : état « à vérifier », aucun démarrage
        }
        if (! hash_equals($merchant, (string) $eventMerchant)) {
            $this->flag($order, $payment, 'merchant_mismatch');

            return 'rejected';
        }
        // Référence du prestataire pas encore enregistrée (notification arrivée avant la réponse de création) : on rejoue la MÊME création idempotente.
        if ($payment->provider_transaction_reference === null) {
            $this->recorder->run($payment);
            $payment->refresh();
        }
        if ($payment->provider_transaction_reference === null) {
            $this->reviewReason = 'reference_unbound';

            return 'review';
        }
        if ($payment->provider_transaction_reference !== $event->reference) {
            $this->flag($order, $payment, 'reference_mismatch');

            return 'rejected';
        }

        return null;
    }

    private function transition(Payment $payment, PaymentState $to): string
    {
        $n = Payment::query()->whereKey($payment->getKey())->where('state', $payment->state->value)->whereIn('state', ['created', 'pending', 'unknown'])
            ->when($to === PaymentState::Pending, fn ($q) => $q->where('state', 'created'))
            ->update(['state' => $to->value, 'pending_at' => $to === PaymentState::Pending ? now() : $payment->pending_at, 'row_version' => DB::raw('row_version + 1'), 'updated_at' => now()]);

        return $n === 1 ? 'applied' : 'ignored';          // régression ignorée (ex. « en attente » après « confirmé »)
    }

    private function fail(Payment $payment, Order $order, string $code): string
    {
        $n = Payment::query()->whereKey($payment->getKey())->whereIn('state', ['created', 'pending', 'unknown'])
            ->update(['state' => PaymentState::Failed->value, 'failed_at' => now(), 'failure_code' => $code, 'row_version' => DB::raw('row_version + 1'), 'updated_at' => now()]);
        if ($n === 1) {
            $order->events()->create(['type' => 'payment_failed', 'actor_id' => null, 'note' => 'Paiement non abouti ('.$payment->provider_reference.').']);

            return 'applied';
        }

        return 'ignored';                                  // un paiement confirmé ne devient jamais « échoué »
    }

    private function succeed(Payment $payment, Order $order): string
    {
        // La notification n'est jamais crue sur parole : statut, montant, devise, référence et environnement sont revérifiés auprès du prestataire.
        $v = $this->gateways->forEnvironment($payment->environment)->verify($payment->verificationReference());
        if ($v->status === ProviderStatus::Indeterminate || $v->status === ProviderStatus::Pending) {
            $this->reviewReason = $v->status === ProviderStatus::Pending ? 'success_not_yet_visible' : 'verification_unavailable';

            return 'review';                               // incertain : on revérifiera, rien ne démarre
        }
        $reason = $this->verifier->inconsistency($payment, $order, $v);
        if ($reason !== null) {
            $this->flag($order, $payment, $reason, ['verified_status' => $v->status->value, 'verified_amount' => $v->amountXof]);

            return 'rejected';
        }

        return match (($this->confirm)($payment->getKey())) {
            'applied' => 'applied',
            'reconciliation' => 'reconciliation',
            default => 'ignored',          // déjà confirmé : aucun second effet
        };
    }

    /** Remboursement effectué CHEZ le prestataire : enregistré et signalé pour traitement ; aucun remboursement n'est exécuté ni déduit par FreeCI. */
    private function refunded(Payment $payment, Order $order): string
    {
        $this->flag($order, $payment, 'refunded_by_provider');

        return 'reconciliation';
    }

    /** @param array<string, mixed> $details */
    private function flag(Order $order, Payment $payment, string $reason, array $details = []): void
    {
        ReconciliationCase::query()->firstOrCreate(
            ['order_id' => $order->getKey(), 'payment_id' => $payment->getKey(), 'reason' => $reason],
            ['details' => $details + ['environment' => $payment->environment, 'payment_state' => $payment->state->value]],
        );
    }
}
