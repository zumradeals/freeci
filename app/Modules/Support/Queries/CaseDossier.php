<?php

namespace App\Modules\Support\Queries;

use App\Modules\Accounts\Models\User;
use App\Modules\Support\Support\CaseRules;
use App\Shared\Dates;
use App\Shared\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * Fiche d'un dossier pour le personnel, en DEUX niveaux :
 * – `meta()` : métadonnées (sans aucun contenu échangé) ;
 * – `content()` : échanges, notes internes, extrait de la commande et pièces — UNIQUEMENT pour la personne affectée, dossier ouvert avec motif, sans conflit.
 * L'extrait de commande se limite à ce qui sert à trancher (accord figé, livraisons, corrections, reports, historique, brief) ; jamais la conversation de la commande.
 */
final class CaseDossier
{
    /** @return array<string, mixed>|null */
    public function meta(User $staff, string $reference): ?array
    {
        $c = DB::table('support_cases')->where('reference', $reference)->first();
        if ($c === null) {
            return null;
        }
        $uid = $staff->getKey();
        $conflict = CaseRules::involves($uid, $c);
        $name = fn (?string $id) => $id === null ? null : DB::table('users')->where('id', $id)->value('name');
        $order = $c->order_id === null ? null : DB::table('orders')->where('id', $c->order_id)->first(['reference', 'state']);
        $d = DB::table('support_decisions')->where('case_id', $c->id)->first();

        return [
            'id' => $c->id, 'reference' => $c->reference, 'kind' => $c->kind, 'kindLabel' => CaseRules::KINDS[$c->kind], 'subject' => $c->subject, 'status' => $c->status, 'statusLabel' => CaseRules::STAFF_STATUS[$c->status],
            'priority' => $c->priority, 'version' => $c->row_version, 'live' => in_array($c->status, CaseRules::LIVE, true), 'opened' => Dates::format(Carbon::parse($c->opened_at)),
            'requester' => $name($c->requester_id) ?? 'Équipe (besoin de suivi)', 'counterparty' => $name($c->counterparty_id), 'orderReference' => $order?->reference, 'orderState' => $order?->state,
            'target' => $c->target_label, 'targetType' => $c->target_type, 'category' => $c->category, 'origin' => $c->origin, 'assignee' => $name($c->assignee_id),
            'isAssignee' => $c->assignee_id === $uid, 'unassigned' => $c->assignee_id === null, 'conflict' => $conflict, 'accessOpen' => $c->assignee_id === $uid && $c->access_opened_at !== null && ! $conflict,
            'disputeLike' => in_array($c->kind, CaseRules::DISPUTE_KINDS, true), 'hasDecision' => $d !== null,
            'decision' => $d === null ? null : ['outcome' => CaseRules::OUTCOMES[$d->outcome], 'reason' => $d->reason, 'from' => $d->order_state_from, 'to' => $d->order_state_to, 'financial' => $d->financial_status === 'to_process' ? CaseRules::FINANCIAL[$d->financial_need] : null,
                'note' => $d->financial_note, 'when' => Dates::format(Carbon::parse($d->created_at))],
            'hold' => DB::table('payout_holds')->where('case_id', $c->id)->first(['created_at', 'released_at']),
            'history' => DB::table('support_events')->where('case_id', $c->id)->whereNotIn('type', ['message'])->orderByDesc('id')->limit(40)->get()
                ->map(fn ($e) => ['what' => $e->note ?? $e->type, 'when' => Dates::format(Carbon::parse($e->occurred_at)), 'who' => $e->actor_id === null ? 'Système' : $name($e->actor_id)])->all(),
        ];
    }

    /** @return array<string, mixed>|null null si l'accès n'est pas ouvert à CETTE personne */
    public function content(User $staff, string $reference): ?array
    {
        $c = DB::table('support_cases')->where('reference', $reference)->first();
        $uid = $staff->getKey();
        if ($c === null || $c->assignee_id !== $uid || $c->access_opened_at === null || CaseRules::involves($uid, $c)) {
            return null;
        }
        $names = DB::table('users')->pluck('name', 'id');
        $messages = DB::table('support_messages')->where('case_id', $c->id)->orderBy('id')->get();
        $files = DB::table('file_assets')->whereIn('support_message_id', $messages->pluck('id'))->get()->groupBy('support_message_id');
        $thread = $messages->map(fn ($m) => [
            'who' => $m->author_id === null ? 'FreeCI' : ($names[$m->author_id] ?? '—'), 'visibility' => $m->visibility, 'body' => $m->body, 'when' => Dates::format(Carbon::parse($m->created_at)),
            'files' => collect($files[$m->id] ?? [])->map(fn ($f) => ['name' => $f->original_name, 'state' => $f->state, 'url' => $f->state === 'clean' ? $this->link($c->reference, $f->id, $uid) : null])->all(),
        ])->all();

        return [
            'accessReason' => $c->access_reason, 'thread' => $thread, 'snapshot' => $c->target_snapshot,
            'order' => in_array($c->kind, CaseRules::DISPUTE_KINDS, true) || $c->kind === 'follow_up' ? ($c->order_id === null ? null : $this->order($c, $uid)) : null,
        ];
    }

    private function link(string $reference, string $fileId, string $uid): string
    {
        return URL::temporarySignedRoute('admin.support.files.download', now()->addMinutes(5), ['reference' => $reference, 'file' => $fileId, 'u' => $uid]);
    }

    /** @return array<string, mixed> */
    private function order(object $c, string $uid): array
    {
        $o = DB::table('orders')->where('id', $c->order_id)->first();
        $a = DB::table('order_agreements')->where('order_id', $o->id)->first();
        $b = DB::table('order_briefs')->where('order_id', $o->id)->first();
        $fileRows = DB::table('file_assets')->where('order_id', $o->id)->whereNotIn('state', ['removed'])->get();
        $fileOut = fn ($f) => ['name' => $f->original_name, 'state' => $f->state, 'url' => $f->state === 'clean' ? $this->link($c->reference, $f->id, $uid) : null];
        $deliveries = DB::table('deliveries')->where('order_id', $o->id)->where('state', 'submitted')->orderBy('version')->get()->map(fn ($d) => [
            'version' => $d->version, 'when' => Dates::format(Carbon::parse($d->submitted_at)), 'message' => $d->message, 'files' => $fileRows->where('delivery_id', $d->id)->map($fileOut)->values()->all(),
        ])->all();
        $titles = ['requested' => 'Demande envoyée', 'accepted' => 'Demande acceptée', 'payment_confirmed' => 'Paiement confirmé', 'work_started' => 'Départ de la réalisation', 'delivery_submitted' => 'Livraison soumise',
            'correction_requested' => 'Correction demandée', 'extension_requested' => 'Report proposé', 'extension_accepted' => 'Report accepté', 'extension_declined' => 'Report refusé', 'validated' => 'Livraison validée', 'closed' => 'Clôture commerciale',
            'dispute_opened' => 'Litige ou annulation ouvert', 'dispute_resumed' => 'Poursuite après dossier', 'dispute_validated' => 'Livraison jugée conforme', 'dispute_cancelled' => 'Annulation décidée', 'disagreement_reported' => 'Désaccord signalé', 'review_overdue' => 'Délai d’examen dépassé'];

        return [
            'reference' => $o->reference, 'state' => $o->state,
            'agreement' => ['title' => $a->service_title, 'scope' => $a->scope, 'price' => Money::xof((int) $a->price_xof)->formatted().' FCFA', 'deliveryDays' => $a->delivery_days, 'revisions' => $a->revisions_included,
                'deliverables' => json_decode((string) $a->deliverables, true) ?: [], 'exclusions' => json_decode((string) $a->exclusions, true) ?: [], 'acceptedAt' => Dates::format(Carbon::parse($a->conditions_accepted_at))],
            'dates' => ['due' => $o->due_at ? Dates::format(Carbon::parse($o->due_at)) : null, 'started' => $o->started_at ? Dates::format(Carbon::parse($o->started_at)) : null],
            'events' => DB::table('order_events')->where('order_id', $o->id)->orderBy('id')->get()->map(fn ($e) => ['what' => $titles[$e->type] ?? $e->type, 'when' => Dates::format(Carbon::parse($e->occurred_at))])->all(),
            'deliveries' => $deliveries,
            'corrections' => DB::table('correction_requests')->where('order_id', $o->id)->orderBy('number')->get()->map(fn ($r) => ['number' => $r->number, 'reason' => $r->reason, 'when' => Dates::format(Carbon::parse($r->created_at))])->all(),
            'extensions' => DB::table('extension_requests')->where('order_id', $o->id)->orderBy('id')->get()->map(fn ($r) => ['state' => $r->state, 'reason' => $r->reason, 'proposed' => Dates::format(Carbon::parse($r->proposed_due_at))])->all(),
            'brief' => $b === null ? null : ['answers' => json_decode((string) $b->answers, true) ?: [], 'notes' => $b->notes, 'files' => $fileRows->whereNull('delivery_id')->map($fileOut)->values()->all()],
        ];
    }
}
