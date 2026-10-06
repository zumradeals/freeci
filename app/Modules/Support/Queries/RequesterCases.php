<?php

namespace App\Modules\Support\Queries;

use App\Modules\Accounts\Models\User;
use App\Modules\Support\Support\CaseRules;
use App\Shared\Dates;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * Suivi d'un dossier par le demandeur (et, pour un litige ou une annulation, par l'autre partie). Jamais de note interne, jamais le nom de l'équipe,
 * jamais un dossier de signalement vu par la personne signalée. La prise en charge est décrite sans rien promettre.
 */
final class RequesterCases
{
    /** @return list<array<string, mixed>> */
    public function list(User $u): array
    {
        $uid = $u->getKey();

        return DB::table('support_cases')->where(fn ($q) => $q->where('requester_id', $uid)->orWhere(fn ($w) => $w->where('counterparty_id', $uid)->whereIn('kind', ['dispute', 'cancellation'])))
            ->orderByDesc('updated_at')->limit(100)->get()->map(fn ($c) => [
                'reference' => $c->reference, 'kind' => CaseRules::KINDS[$c->kind], 'subject' => $c->subject, 'status' => CaseRules::statusFor($c->status, $c->kind, $c->assignee_id !== null),
                'live' => in_array($c->status, CaseRules::LIVE, true), 'role' => $c->requester_id === $uid ? 'requester' : 'counterparty', 'when' => Dates::format(Carbon::parse($c->updated_at)),
            ])->all();
    }

    /** @return array<string, mixed>|null */
    public function show(User $u, string $reference): ?array
    {
        $uid = $u->getKey();
        $c = DB::table('support_cases')->where('reference', $reference)->first();
        if ($c === null) {
            return null;
        }
        $role = $c->requester_id === $uid ? 'requester' : ($c->counterparty_id === $uid && in_array($c->kind, ['dispute', 'cancellation'], true) ? 'counterparty' : null);
        if ($role === null) {
            return null;
        }
        $visible = $role === 'requester' ? ['requester', 'parties'] : ['parties'];
        $names = DB::table('users')->whereIn('id', array_filter([$c->requester_id, $c->counterparty_id]))->pluck('name', 'id');
        $messages = DB::table('support_messages')->where('case_id', $c->id)->whereIn('visibility', $visible)->orderBy('id')->get();
        $files = DB::table('file_assets')->whereIn('support_message_id', $messages->pluck('id'))->get()->groupBy('support_message_id');
        $thread = $messages->map(function ($m) use ($uid, $names, $files) {
            $who = $m->author_id === null ? 'FreeCI' : ($m->author_id === $uid ? 'Vous' : ($names[$m->author_id] ?? 'Équipe d’assistance'));

            return [
                'who' => $who, 'mine' => $m->author_id === $uid, 'staff' => $m->author_id !== null && ! isset($names[$m->author_id]), 'body' => $m->body, 'when' => Dates::format(Carbon::parse($m->created_at)),
                'shared' => $m->visibility === 'parties',
                'files' => collect($files[$m->id] ?? [])->map(fn ($f) => [
                    'name' => $f->original_name, 'state' => $f->state, 'url' => $f->state === 'clean' ? URL::temporarySignedRoute('support.files.download', now()->addMinutes(5), ['file' => $f->id, 'u' => $uid]) : null,
                ])->all(),
            ];
        })->all();
        $events = DB::table('support_events')->where('case_id', $c->id)->whereIn('visibility', $visible)->whereNotIn('type', ['message'])->orderBy('id')->get()
            ->map(fn ($e) => ['what' => $e->note ?? $e->type, 'when' => Dates::format(Carbon::parse($e->occurred_at))])->all();
        $d = DB::table('support_decisions')->where('case_id', $c->id)->first();
        $order = $c->order_id === null ? null : DB::table('orders')->where('id', $c->order_id)->first(['reference', 'state']);

        return [
            'reference' => $c->reference, 'kind' => $c->kind, 'kindLabel' => CaseRules::KINDS[$c->kind], 'subject' => $c->subject, 'role' => $role, 'live' => in_array($c->status, CaseRules::LIVE, true),
            'status' => CaseRules::statusFor($c->status, $c->kind, $c->assignee_id !== null), 'statusKey' => $c->status, 'opened' => Dates::format(Carbon::parse($c->opened_at)),
            'orderReference' => $order?->reference, 'orderState' => $order?->state, 'target' => $c->target_label,
            'thread' => $thread, 'events' => $events, 'disputeLike' => in_array($c->kind, ['dispute', 'cancellation'], true), 'canAttach' => true,
            'decision' => $d === null ? null : [
                'outcome' => CaseRules::OUTCOMES[$d->outcome], 'reason' => $d->reason, 'when' => Dates::format(Carbon::parse($d->created_at)),
                'orderEffect' => $d->order_state_to === null ? null : match ($d->order_state_to) {
                    'cancelled' => 'La commande est annulée.', 'closed' => 'La livraison est jugée conforme : la commande est clôturée.', default => 'La commande reprend là où elle en était.',
                },
                'financial' => $d->financial_status === 'to_process' ? CaseRules::FINANCIAL[$d->financial_need] : null,
            ],
        ];
    }
}
