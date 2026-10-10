<?php

namespace App\Modules\Admin\Queries;

use App\Modules\Accounts\Referrals\ReferralCodes;
use App\Modules\Finance\Commission\CommissionTerms;
use App\Shared\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** « Campagnes et parrainages » (F-14) : compteurs réels, campagnes, attributions récentes. Aucune donnée privée (prénom et initiale, jamais d'e-mail). */
final class ReferralAdmin
{
    /** @return array<string, mixed> */
    public function __invoke(): array
    {
        $base = CommissionTerms::baseBp();
        $grants = DB::table('commission_grants')->where('state', 'active')->get(['id', 'total', 'consumed']);
        $remaining = 0;
        foreach ($grants as $g) {
            $remaining += max(0, (int) $g->total - (int) $g->consumed - (int) DB::table('commission_grant_uses')->where('grant_id', $g->id)->where('state', 'reserved')->count());
        }
        $offered = (int) DB::table('orders as o')->join('order_agreements as a', 'a.order_id', '=', 'o.id')->where('o.environment', 'live')->where('o.closure_reason', 'validated')->where('o.closed_at', '>=', now()->subDays(30))
            ->whereNotNull('a.commission_base_bp')->sum(DB::raw('(((a.price_xof * a.commission_base_bp + 5000) / 10000) - ((a.price_xof * a.commission_bp + 5000) / 10000))'));
        $pct = fn (int $bp) => rtrim(rtrim(number_format($bp / 100, 2, ',', ''), '0'), ',');
        $today = Carbon::now('Africa/Abidjan')->format('Y-m-d');

        return [
            'stats' => ['active' => $grants->count(), 'remaining' => $remaining, 'offered' => Money::xof($offered)->formatted().' FCFA', 'qualified' => (int) DB::table('referrals')->where('state', 'qualified')->count()],
            'campaigns' => DB::table('promo_campaigns')->orderByDesc('created_at')->orderBy('id')->limit(100)->get()->map(function ($c) use ($today, $pct) {
                [$label, $tone] = $c->state === 'suspended' ? ['Suspendue', 'neutral'] : ($c->starts_on > $today ? ['Programmée', 'info'] : ($c->ends_on < $today ? ['Terminée', 'neutral'] : ((int) $c->uses >= (int) $c->max_uses ? ['Épuisée', 'warning'] : ['Active', 'success'])));

                return ['id' => (string) $c->id, 'code' => $c->code, 'rate' => $pct((int) $c->rate_bp), 'rateRaw' => $pct((int) $c->rate_bp), 'orders' => (int) $c->free_orders, 'from' => $c->starts_on, 'to' => $c->ends_on,
                    'fromLabel' => Carbon::parse($c->starts_on)->translatedFormat('j M Y'), 'toLabel' => Carbon::parse($c->ends_on)->translatedFormat('j M Y'), 'uses' => (int) $c->uses, 'max' => (int) $c->max_uses, 'note' => $c->note,
                    'state' => $c->state, 'label' => $label, 'tone' => $tone, 'editable' => (int) $c->uses === 0, 'suspended' => $c->state === 'suspended'];
            })->all(),
            'grants' => DB::table('commission_grants as g')->join('users as u', 'u.id', '=', 'g.user_id')->leftJoin('promo_campaigns as c', 'c.id', '=', 'g.campaign_id')->leftJoin('referrals as r', 'r.id', '=', 'g.referral_id')->leftJoin('users as other', 'other.id', '=', DB::raw("CASE WHEN g.source = 'referral_referee' THEN r.referrer_id ELSE r.referee_id END"))
                ->orderByDesc('g.created_at')->orderBy('g.id')->limit(50)->get(['g.id', 'g.source', 'g.total', 'g.consumed', 'g.rate_bp', 'g.state', 'g.created_at', 'g.revoked_reason', 'u.name', 'c.code', 'other.name as other_name'])
                ->map(fn ($g) => ['id' => (string) $g->id, 'who' => ReferralCodes::shortName((string) $g->name), 'origin' => match ($g->source) {
                    'campaign' => 'Code '.$g->code, 'referral_referee' => 'Parrainage (filleul de '.ReferralCodes::shortName((string) $g->other_name).')', default => 'Parrainage (parrain de '.ReferralCodes::shortName((string) $g->other_name).')'
                },
                    'balance' => (int) $g->total - (int) $g->consumed, 'total' => (int) $g->total, 'rate' => $pct((int) $g->rate_bp), 'when' => Carbon::parse($g->created_at)->translatedFormat('j M Y'), 'active' => $g->state === 'active', 'reason' => $g->revoked_reason])->all(),
            'settings' => ['enabled' => (bool) config('freeci.referral.enabled'), 'orders' => (int) config('freeci.referral.free_orders'), 'rate' => $pct((int) config('freeci.referral.rate_bp')), 'max' => (int) config('freeci.referral.max_qualified'), 'base' => $pct($base)],
        ];
    }
}
