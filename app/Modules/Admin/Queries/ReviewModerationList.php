<?php

namespace App\Modules\Admin\Queries;

use App\Modules\Orders\Support\ReviewVisibility;
use App\Modules\Support\Support\CaseRules;
use App\Shared\Dates;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Liste de modération des avis (administrateurs) : contenu intégral lisible, signalements en cours, historique. Les commandes de test n'y figurent que si demandé. */
final class ReviewModerationList
{
    /** @return LengthAwarePaginator<int, array<string, mixed>> */
    public function page(string $filter, int $perPage): LengthAwarePaginator
    {
        $q = DB::table('reviews')->join('orders', 'orders.id', '=', 'reviews.order_id')->join('users as a', 'a.id', '=', 'reviews.author_id')->join('users as s', 's.id', '=', 'reviews.subject_id')
            ->select('reviews.*', 'orders.reference as order_reference', 'a.name as author_name', 's.name as subject_name')
            ->selectRaw("(select count(*) from support_cases c where c.kind = 'report' and c.status in ('open','in_review','awaiting_requester','awaiting_party') and ((c.target_type = 'review' and c.target_id = reviews.id::text) or (c.target_type = 'reply' and c.target_id = reviews.id::text))) as live_reports");
        match ($filter) {
            'reported' => $q->whereRaw("exists (select 1 from support_cases c where c.kind = 'report' and c.status in ('open','in_review','awaiting_requester','awaiting_party') and c.target_type in ('review','reply') and c.target_id = reviews.id::text)"),
            'hidden' => $q->where(fn ($w) => $w->whereNotNull('reviews.hidden_at')->orWhereExists(fn ($x) => $x->select(DB::raw(1))->from('review_responses as rr')->whereColumn('rr.review_id', 'reviews.id')->whereNotNull('rr.hidden_at'))),
            'test' => $q->where('reviews.counts_public', false),
            default => $q->where('reviews.counts_public', true),
        };
        $p = $q->orderByDesc('reviews.created_at')->orderByDesc('reviews.id')->paginate($perPage)->withQueryString();
        $replies = DB::table('review_responses')->whereIn('review_id', $p->getCollection()->pluck('id')->all())->get()->keyBy('review_id');
        $history = DB::table('review_moderations as m')->join('users as u', 'u.id', '=', 'm.actor_id')->whereIn('m.review_id', $p->getCollection()->pluck('id')->all())->orderBy('m.id')
            ->get(['m.review_id', 'm.target', 'm.action', 'm.category', 'm.reason', 'u.name', 'm.created_at'])->groupBy('review_id');

        return $p->through(fn ($r) => [
            'id' => $r->id, 'order' => $r->order_reference, 'author' => $r->author_name, 'subject' => $r->subject_name, 'rating' => (int) $r->rating, 'comment' => $r->comment, 'origin' => $r->origin === 'mission' ? 'Mission' : 'Service',
            'public' => (bool) $r->counts_public, 'waiting' => $r->counts_public && now()->lessThan($r->visible_at), 'visibleAt' => Dates::format(Carbon::parse($r->visible_at)), 'hidden' => $r->hidden_at !== null, 'reports' => (int) $r->live_reports,
            'reply' => isset($replies[$r->id]) ? ['body' => $replies[$r->id]->body, 'hidden' => $replies[$r->id]->hidden_at !== null] : null,
            'history' => collect($history[$r->id] ?? [])->map(fn ($h) => ['who' => $h->name, 'target' => $h->target === 'review' ? 'avis' : 'réponse', 'action' => $h->action === 'hidden' ? 'masqué' : 'rétabli',
                'category' => $h->category ? ReviewVisibility::MODERATION_CATEGORIES[$h->category] : null, 'reason' => $h->reason, 'when' => Dates::format(Carbon::parse($h->created_at))])->all(),
        ]);
    }

    /** @return array{reported: int, hidden: int} */
    public function counts(): array
    {
        return [
            'reported' => (int) DB::table('support_cases')->where('kind', 'report')->whereIn('target_type', ['review', 'reply'])->whereIn('status', CaseRules::LIVE)->distinct('target_id')->count('target_id'),
            'hidden' => (int) DB::table('reviews')->whereNotNull('hidden_at')->count(),
        ];
    }
}
