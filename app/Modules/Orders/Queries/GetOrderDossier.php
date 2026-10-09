<?php

namespace App\Modules\Orders\Queries;

use App\Integrations\FileScan\FileScanner;
use App\Modules\Accounts\Models\User;
use App\Modules\Files\Actions\DownloadBriefFile;
use App\Modules\Files\Enums\FileState;
use App\Modules\Finance\Enums\PaymentState;
use App\Modules\Finance\Models\LedgerBatch;
use App\Modules\Finance\PaymentGate;
use App\Modules\Finance\Queries\ClientFinance;
use App\Modules\Finance\Support\FinanceLabels;
use App\Modules\Messaging\Queries\Inbox;
use App\Modules\Orders\Actions\BriefStatus;
use App\Modules\Orders\Actions\ExpireOverdueOrders;
use App\Modules\Orders\Actions\RecordReviewFollowUps;
use App\Modules\Orders\Data\OrderDossier;
use App\Modules\Orders\Enums\OrderState;
use App\Modules\Orders\Exceptions\OrderForbidden;
use App\Modules\Orders\Models\Order;
use App\Modules\Support\Queries\DisputeOptions;
use App\Shared\Dates;
use App\Shared\Money;
use Illuminate\Support\Facades\DB;

/** `Orders\GetOrderDossier` : lecture BORNÉE AUX PARTIES. Inexistant et interdit répondent pareil (docs/04 §8.5). */
final class GetOrderDossier
{
    public function __construct(private ExpireOverdueOrders $expire, private PaymentGate $gate, private FileScanner $scanner, private DownloadBriefFile $downloads, private DeliverySection $deliverySection, private RecordReviewFollowUps $followUps) {}

    public function __invoke(User $viewer, string $reference): OrderDossier
    {
        $order = Order::query()->where('reference', $reference)
            ->where(fn ($q) => $q->where('client_id', $viewer->getKey())->orWhere('freelancer_id', $viewer->getKey()))
            ->first();
        if ($order === null) {
            throw new OrderForbidden;
        }
        $this->expire->forOrder($order->getKey());
        $this->followUps->forOrder($order->getKey());
        $order = Order::with(['agreement', 'brief', 'events.actor', 'client', 'freelancer', 'files'])->findOrFail($order->getKey());

        $isFreelancer = $order->freelancer_id === $viewer->getKey();
        $perspective = $isFreelancer ? 'freelancer' : 'client';
        $a = $order->agreement;
        [$tone, $icon] = $order->state->tone($isFreelancer);

        $items = collect($order->brief->answers);
        $brief = BriefStatus::of($order);

        $names = [$order->client_id => $order->client->name, $order->freelancer_id => $order->freelancer->name];
        $events = $order->events->map(function ($e) use ($names, $isFreelancer) {
            $from = $e->from_state ? OrderState::from($e->from_state) : null;
            $to = $e->to_state ? OrderState::from($e->to_state) : null;
            $who = $e->actor_id ? ($names[$e->actor_id] ?? 'Utilisateur') : 'FreeCI';
            $title = match ($e->type) {
                'requested' => "Demande envoyée par {$who}",
                'accepted' => "Demande acceptée par {$who}",
                'payment_started' => 'Paiement simulé démarré',
                'payment_confirmed' => 'Paiement simulé confirmé (vérifié côté serveur)',
                'payment_failed' => 'Paiement simulé non abouti',
                'brief_awaited' => 'Brief à compléter avant le départ',
                'work_started' => 'Départ de la réalisation enregistré',
                'brief_file_added' => "Fichier ajouté au brief par {$who}",
                'brief_file_clean' => 'Fichier contrôlé : contrôle de sécurité réussi',
                'brief_file_rejected' => 'Fichier refusé par le contrôle de sécurité',
                'brief_file_removed' => "Fichier retiré par {$who}",
                'dispute_opened' => 'Litige ou demande d’annulation ouvert (dossier '.($e->meta['case'] ?? '?').')',
                'dispute_resumed' => 'Décision du support : poursuite de la prestation',
                'dispute_validated' => 'Décision du support : livraison jugée conforme',
                'dispute_cancelled' => 'Décision du support : commande annulée après paiement',
                'delivery_submitted' => 'Livraison v'.($e->meta['version'] ?? '?').' soumise par '.$who,
                'correction_requested' => 'Correction n° '.($e->meta['correction_number'] ?? '?').' demandée par '.$who.' (livraison v'.($e->meta['delivery_version'] ?? '?').')',
                'extension_requested' => "Report d’échéance proposé par {$who}",
                'extension_accepted' => "Report d’échéance accepté par {$who}",
                'extension_declined' => "Report d’échéance refusé par {$who}",
                'extension_withdrawn' => 'Proposition de report retirée',
                'validated' => 'Livraison v'.($e->meta['delivery_version'] ?? '?')." validée par {$who}",
                'closed' => 'Commande clôturée (clôture commerciale)',
                'disagreement_reported' => "Désaccord signalé par {$who} (livraison v".($e->meta['delivery_version'] ?? '?').') : besoin de suivi enregistré',
                'review_overdue' => 'Délai d’examen dépassé : besoin de suivi enregistré',
                'proposal_selected' => 'Proposition v'.($e->meta['proposal_version'] ?? '?')." retenue par {$who}",
                'declined' => "Demande refusée par {$who}",
                'withdrawn' => "Demande retirée par {$who}",
                'cancelled' => "Commande annulée par {$who}",
                'expired' => 'Délai dépassé : demande expirée',
                default => $e->type,
            };

            return [
                'title' => $title, 'when' => Dates::format($e->occurred_at),
                'from' => $from?->label($isFreelancer), 'to' => $to?->label($isFreelancer), 'toTone' => $to?->tone($isFreelancer)[0],
                'note' => $e->note && $e->type !== 'withdrawn' ? $e->note : null, 'now' => false,
            ];
        })->reverse()->values()->all();
        if ($events !== []) {
            $events[0]['now'] = true;
        }

        $payment = $order->payments()->orderByDesc('id')->first();
        $paymentOpen = $payment?->state->isOpen() ?? false;
        $paymentConfirmed = $payment?->state === PaymentState::Confirmed;
        $canPay = ! $isFreelancer && $order->state === OrderState::AwaitingPayment && $this->gate->allows($order) && ! $paymentOpen && ! $paymentConfirmed;
        $confirmedXof = (int) LedgerBatch::query()->where('order_id', $order->getKey())->where('kind', 'payment_confirmed')->join('ledger_lines', 'ledger_lines.batch_id', '=', 'ledger_batches.id')->whereIn('ledger_lines.account', ['escrow', 'escrow_simulated'])->sum('ledger_lines.amount_xof');

        $uploadsEnabled = $this->scanner->isOperational();
        $canUpload = ! $isFreelancer && $uploadsEnabled && in_array($order->state, [OrderState::AwaitingAcceptance, OrderState::AwaitingPayment, OrderState::AwaitingBrief], true) && $order->started_at === null;
        $files = $order->files->whereNotIn('state', [FileState::Removed])->map(function ($f) use ($viewer, $order, $isFreelancer, $canUpload) {
            [$tone, $icon] = match ($f->state) {
                FileState::Clean => ['success', 'shield'], FileState::Rejected => ['error', 'error'], default => ['warning', 'clock']
            };
            $kb = $f->size_bytes / 1024;

            return [
                'id' => $f->id, 'name' => $f->original_name, 'size' => $kb >= 1024 ? number_format($kb / 1024, 1, ',', ' ').' Mo' : number_format($kb, 0, ',', ' ').' Ko',
                'label' => $f->state->label(), 'tone' => $tone, 'icon' => $icon,
                'url' => $f->state->downloadable() ? $this->downloads->link($viewer, $order->reference, $f->id) : null,
                'canRemove' => ! $isFreelancer && $canUpload && $f->state !== FileState::Rejected ? true : false,
                'note' => $f->state === FileState::Rejected ? 'Refusé par le contrôle de sécurité : non téléchargeable.' : (! $f->state->downloadable() ? 'Non téléchargeable avant la fin du contrôle de sécurité.' : null),
            ];
        })->values()->all();

        $actions = [];
        if ($order->state === OrderState::AwaitingAcceptance) {
            $actions = $isFreelancer
                ? [['kind' => 'accept', 'label' => 'Accepter la demande', 'primary' => true], ['kind' => 'decline', 'label' => 'Refuser la demande', 'primary' => false]]
                : [['kind' => 'withdraw', 'label' => 'Retirer la demande', 'primary' => false]];
        } elseif ($order->state === OrderState::AwaitingPayment && ! $isFreelancer) {
            $actions = [['kind' => 'cancel', 'label' => 'Annuler la commande', 'primary' => false]];
        }

        return new OrderDossier(
            reference: $order->reference, version: $order->row_version, perspective: $perspective,
            stateValue: $order->state->value, stateLabel: $order->state->label($isFreelancer), tone: $tone, icon: $icon,
            isDemo: $order->is_demo, environment: $order->environment, title: $a->service_title, categoryName: $a->category_name,
            otherPartyLabel: $isFreelancer ? 'Client' : 'Freelance', otherPartyName: $isFreelancer ? $order->client->name : $order->freelancer->name, otherPartyId: (string) ($isFreelancer ? $order->client_id : $order->freelancer_id),
            clientName: $order->client->name, freelancerName: $order->freelancer->name,
            price: Money::xof($a->price_xof), deliveryDays: $a->delivery_days, revisionsIncluded: $a->revisions_included,
            scope: $a->scope, deliverables: $a->deliverables, exclusions: $a->exclusions,
            serviceVersion: (int) $a->service_row_version, conditionsVersion: $a->conditions_version, conditionsAcceptedAt: $a->conditions_accepted_at,
            requestedAt: $order->requested_at, responseDeadline: $order->response_deadline_at, acceptedAt: $order->accepted_at,
            paymentDeadline: $order->payment_deadline_at, closureReason: $order->closure_reason?->label(), closureNote: $order->closure_note,
            briefItems: $items->all(), briefNotes: $order->brief->notes, briefComplete: $brief['complete'], briefMissing: $brief['missing'],
            events: $events, actions: $actions,
            stepIndex: match ($order->state) {
                OrderState::Disputed => 3,
                OrderState::AwaitingAcceptance => 0, OrderState::AwaitingPayment => 1, OrderState::AwaitingBrief => 2, OrderState::InProgress, OrderState::RevisionRequested => 3,
                OrderState::Delivered => 4, OrderState::Validated => 5, OrderState::Closed => 7, default => 0
            },
            isFinal: $order->state->isFinal(),
            startedAt: $order->started_at, dueAt: $order->due_at,
            payment: $payment === null ? null : [
                'label' => $payment->state->label(), 'tone' => $payment->state->tone()[0], 'icon' => $payment->state->tone()[1],
                'state' => $payment->state->value, 'reference' => $payment->reference, 'at' => $payment->created_at,
            ],
            paymentOpen: $paymentOpen, canPay: $canPay, confirmedXof: $confirmedXof, finance: $this->financeFor($order, $isFreelancer),
            files: $files, uploadsEnabled: $uploadsEnabled, canUpload: $canUpload,
            briefRequiresFiles: (bool) $a->brief_requires_files,
            uploadLimits: $this->uploadLimits(),
            delivery: ($this->deliverySection)($order, $viewer, $isFreelancer),
            origin: $order->origin, proposalNumber: $order->origin === 'mission' ? (int) DB::table('proposal_versions')->where('id', $order->proposal_version_id)->value('number') : null,
            missionId: $order->origin === 'mission' && ! $isFreelancer ? $order->mission_id : null,
            messageUnread: app(Inbox::class)->unreadForOrder($viewer, $order->getKey()),
            supportCase: app(DisputeOptions::class)->for($order->getKey(), $order->state->value)['live'],
            disputeKinds: app(DisputeOptions::class)->for($order->getKey(), $order->state->value)['kinds'],
            tierName: $a->tier_name, basePrice: $a->base_price_xof === null ? null : (int) $a->base_price_xof, selectedOptions: $a->selected_options ?? [],
        );
    }

    private function uploadLimits(): string
    {
        $mb = (int) config('freeci.files.max_mb', 10);

        return "PDF, images (JPG, PNG, WebP) ou plan DWG — {$mb} Mo maximum par fichier.";
    }

    /** @return array<string, mixed> remboursements (les deux parties) ; reversement (freelance seulement), états établis uniquement */
    private function financeFor(Order $order, bool $isFreelancer): array
    {
        $f = app(ClientFinance::class)->forOrder($order->getKey());
        $payout = null;
        if ($isFreelancer) {
            $p = DB::table('financial_operations')->where('order_id', $order->getKey())->where('kind', 'payout')->whereIn('state', ['requested', 'approved', 'in_progress', 'to_verify', 'confirmed'])->first(['state', 'amount_xof', 'reference']);
            $payout = $p === null ? null : ['label' => FinanceLabels::PARTY_STATES[$p->state], 'amount' => (int) $p->amount_xof, 'reference' => $p->reference, 'state' => $p->state];
        }

        return ['refunds' => $f['refunds'], 'refunded' => $f['refunded'], 'refund_open' => $f['refund_open'], 'payout' => $payout];
    }
}
