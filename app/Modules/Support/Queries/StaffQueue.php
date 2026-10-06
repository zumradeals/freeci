<?php

namespace App\Modules\Support\Queries;

use App\Modules\Accounts\Models\User;
use App\Modules\Support\Support\CaseRules;
use App\Shared\Dates;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** File des dossiers pour le personnel : métadonnées seulement (jamais le contenu des échanges). */
final class StaffQueue
{
    /** @return array{open: int, mine: int, unassigned: int, toProcess: int, followUps: int} */
    public function counts(User $staff): array
    {
        $live = DB::table('support_cases')->whereIn('status', CaseRules::LIVE);

        return [
            'open' => (clone $live)->count(),
            'mine' => (clone $live)->where('assignee_id', $staff->getKey())->count(),
            'unassigned' => (clone $live)->whereNull('assignee_id')->count(),
            'toProcess' => DB::table('support_decisions')->where('financial_status', 'to_process')->count(),
            'followUps' => DB::table('order_follow_ups')->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('support_cases')->where('support_cases.origin', 'follow_up')->whereRaw('support_cases.origin_ref = order_follow_ups.id::text'))->count(),
        ];
    }

    /** @param array{kind?: ?string, status?: ?string, who?: ?string, q?: ?string} $f */
    public function list(User $staff, array $f, int $perPage): LengthAwarePaginator
    {
        $q = DB::table('support_cases as c')->leftJoin('users as r', 'r.id', '=', 'c.requester_id')->leftJoin('users as a', 'a.id', '=', 'c.assignee_id')
            ->when(isset(CaseRules::KINDS[$f['kind'] ?? '']), fn ($w) => $w->where('c.kind', $f['kind']))
            ->when(in_array($f['status'] ?? '', array_keys(CaseRules::STAFF_STATUS), true), fn ($w) => $w->where('c.status', $f['status']), fn ($w) => $w->whereIn('c.status', CaseRules::LIVE))
            ->when(($f['who'] ?? '') === 'mine', fn ($w) => $w->where('c.assignee_id', $staff->getKey()))
            ->when(($f['who'] ?? '') === 'unassigned', fn ($w) => $w->whereNull('c.assignee_id'))
            ->when(isset($f['q']) && trim($f['q']) !== '', fn ($w) => $w->where(fn ($x) => $x->where('c.reference', 'ilike', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_substr(trim($f['q']), 0, 40)).'%')
                ->orWhere('c.subject', 'ilike', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_substr(trim($f['q']), 0, 40)).'%')))
            ->orderByRaw("case c.priority when 'high' then 0 else 1 end")->orderBy('c.opened_at')
            ->select('c.*', 'r.name as requester_name', 'a.name as assignee_name');

        return $q->paginate($perPage)->withQueryString()->through(fn ($c) => [
            'reference' => $c->reference, 'kind' => CaseRules::KINDS[$c->kind], 'kindKey' => $c->kind, 'subject' => $c->subject, 'status' => CaseRules::STAFF_STATUS[$c->status], 'priority' => $c->priority,
            'requester' => $c->requester_name ?? 'Équipe', 'assignee' => $c->assignee_name, 'mine' => $c->assignee_id === $staff->getKey(), 'since' => Dates::format(Carbon::parse($c->opened_at)),
            'age' => (int) Carbon::parse($c->opened_at)->diffInDays(now()), 'conflict' => CaseRules::involves($staff->getKey(), $c), 'origin' => $c->origin,
        ]);
    }

    /** Besoins de suivi déjà enregistrés (origine conservée), avec le dossier éventuellement ouvert. Le contenu des notes n'est jamais affiché ici. */
    public function followUps(int $limit = 50): array
    {
        $kinds = ['review_silence' => 'Silence du client après le délai d’examen', 'client_disagreement' => 'Désaccord signalé après épuisement des corrections'];

        return DB::table('order_follow_ups as f')->join('orders as o', 'o.id', '=', 'f.order_id')
            ->leftJoin('support_cases as c', fn ($j) => $j->on(DB::raw('c.origin_ref'), '=', DB::raw('f.id::text'))->where('c.origin', 'follow_up'))
            ->orderByDesc('f.recorded_at')->limit($limit)->get(['f.id', 'f.kind', 'f.recorded_at', 'o.reference', 'c.reference as case_reference'])
            ->map(fn ($r) => ['id' => $r->id, 'order' => $r->reference, 'kind' => $kinds[$r->kind] ?? $r->kind, 'when' => Dates::format(Carbon::parse($r->recorded_at)), 'case' => $r->case_reference])->all();
    }

    /** Décisions dont la suite financière reste « à traiter » : rien n'a été remboursé ni versé par FreeCI à ce stade. */
    public function toProcess(): array
    {
        return DB::table('support_decisions as d')->join('support_cases as c', 'c.id', '=', 'd.case_id')->leftJoin('orders as o', 'o.id', '=', 'c.order_id')
            ->where('d.financial_status', 'to_process')->orderBy('d.created_at')
            ->get(['c.reference', 'o.reference as order', 'd.financial_need', 'd.financial_note', 'd.created_at', 'c.id as case_id'])
            ->map(fn ($r) => ['reference' => $r->reference, 'order' => $r->order, 'need' => CaseRules::FINANCIAL[$r->financial_need], 'note' => $r->financial_note, 'when' => Dates::format(Carbon::parse($r->created_at)),
                'hold' => DB::table('payout_holds')->where('case_id', $r->case_id)->whereNull('released_at')->exists()])->all();
    }
}
