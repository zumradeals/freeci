<?php

namespace App\Modules\Missions\Queries;

use App\Modules\Accounts\Models\User;
use App\Shared\Dates;
use App\Shared\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Lectures du plan de jalons (F-13) : suivi pour le client et le freelance de la mission ; bandeau d'une commande de jalon ; éligibilité à l'avis unique. */
final class PlanQueries
{
    private const STATES = ['active' => ['En cours', 'info'], 'paused' => ['En pause', 'warning'], 'stopped' => ['Arrêté', 'neutral'], 'completed' => ['Terminé', 'success']];

    /** @return array<string, mixed>|null */
    public function forMission(User $u, string $missionId): ?array
    {
        $plan = DB::table('mission_plans')->where('mission_id', $missionId)->where('state', '<>', 'discarded')->first();
        if ($plan === null || ! in_array($u->getKey(), [$plan->client_id, $plan->freelancer_id], true)) {
            return null;
        }
        $isClient = $u->getKey() === $plan->client_id;
        $mission = DB::table('missions as m')->join('mission_versions as v', 'v.id', '=', 'm.published_version_id')->where('m.id', $missionId)->first(['m.slug', 'v.title']);
        $items = DB::table('mission_plan_items as i')->leftJoin('orders as o', 'o.id', '=', 'i.order_id')->where('i.plan_id', $plan->id)->orderBy('i.rank')
            ->get(['i.id', 'i.rank', 'i.title', 'i.scope', 'i.price_xof', 'i.delivery_days', 'i.state', 'o.reference', 'o.state as order_state', 'o.payment_deadline_at', 'o.closed_at']);
        $validated = $items->where('state', 'validated');
        $paidSum = (int) $validated->sum('price_xof');
        $reopenUntil = $plan->state === 'paused' && $plan->paused_at !== null ? Carbon::parse($plan->paused_at)->addDays((int) config('freeci.missions.milestones.reopen_days')) : null;
        $current = $items->firstWhere('state', 'open');
        $stoppable = $isClient && in_array($plan->state, ['active', 'paused'], true) && $validated->isNotEmpty()
            && ($plan->state === 'paused' || $current === null || $current->order_state === 'awaiting_payment');

        return [
            'missionId' => $missionId, 'slug' => $mission->slug, 'title' => $mission->title, 'isClient' => $isClient, 'state' => $plan->state, 'stateLabel' => self::STATES[$plan->state][0], 'tone' => self::STATES[$plan->state][1],
            'total' => Money::xof((int) $plan->total_xof)->formatted().' FCFA', 'validatedCount' => $validated->count(), 'count' => $items->count(), 'validatedSum' => Money::xof($paidSum)->formatted().' FCFA',
            'percent' => $plan->total_xof > 0 ? (int) round($paidSum * 100 / $plan->total_xof) : 0, 'stopReason' => $plan->stop_reason,
            'canReopen' => $isClient && $plan->state === 'paused' && $reopenUntil !== null && $reopenUntil->gt(now()), 'reopenUntil' => $reopenUntil ? Dates::format($reopenUntil) : null,
            'canStop' => $stoppable, 'items' => $items->map(fn ($i) => [
                'rank' => (int) $i->rank, 'title' => $i->title, 'scope' => $i->scope, 'price' => Money::xof((int) $i->price_xof)->formatted().' FCFA', 'days' => (int) $i->delivery_days, 'state' => $i->state,
                'reference' => $i->reference, 'orderState' => $i->order_state, 'payBefore' => $i->payment_deadline_at && $i->order_state === 'awaiting_payment' ? Dates::format(Carbon::parse($i->payment_deadline_at)) : null,
                'closed' => $i->closed_at ? Dates::short(Carbon::parse($i->closed_at)) : null,
            ])->all(),
        ];
    }

    /** Bandeau d'une commande de jalon : « Jalon 2 sur 3 ». @return array{rank: int, count: int, mission: string, reviewAllowed: bool}|null */
    public function forReference(string $reference): ?array
    {
        $r = DB::table('orders as o')->join('mission_plan_items as i', 'i.id', '=', 'o.milestone_item_id')->join('mission_plans as p', 'p.id', '=', 'i.plan_id')->where('o.reference', $reference)
            ->first(['i.rank', 'i.plan_id', 'p.mission_id', 'o.milestone_item_id']);
        if ($r === null) {
            return null;
        }

        return ['rank' => (int) $r->rank, 'count' => (int) DB::table('mission_plan_items')->where('plan_id', $r->plan_id)->count(), 'mission' => (string) $r->mission_id, 'reviewAllowed' => self::reviewAllowed($r)];
    }

    /** L'avis est UNIQUE pour tout le plan : jalon final d'un plan terminé, ou dernier jalon validé d'un plan arrêté. Hors jalon : toujours vrai. */
    public static function reviewAllowed(object $order): bool
    {
        if (($order->milestone_item_id ?? null) === null) {
            return true;
        }
        $item = DB::table('mission_plan_items')->where('id', $order->milestone_item_id)->first(['plan_id', 'rank']);
        $plan = DB::table('mission_plans')->where('id', $item->plan_id)->first(['state']);
        if (! in_array($plan->state, ['completed', 'stopped'], true)) {
            return false;
        }
        $last = (int) DB::table('mission_plan_items')->where('plan_id', $item->plan_id)->where('state', 'validated')->max('rank');

        return $last === (int) $item->rank;
    }
}
