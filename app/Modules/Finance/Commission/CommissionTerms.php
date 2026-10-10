<?php

namespace App\Modules\Finance\Commission;

use Illuminate\Support\Facades\DB;

/**
 * Point UNIQUE de décision du taux de commission d'une nouvelle commande (F-14). Sans attribution disponible : le taux normal. Avec une attribution (parrainage ou code promotionnel) : le taux offert,
 * qui est FIGÉ dans l'accord comme avant ; une unité est RÉSERVÉE pour la commande (consommée au paiement, rendue si la commande expire ou est annulée avant). Le prix payé par le client ne change jamais.
 */
final class CommissionTerms
{
    public static function baseBp(): int
    {
        return (int) config('freeci.finance.commission_bp');
    }

    /**
     * À appeler dans la transaction de création, une fois la commande créée et avant son accord.
     *
     * @return array{commission_bp: int, commission_policy: string, commission_base_bp: ?int, commission_reason: ?string}
     */
    public function forOrder(string $orderId, string $freelancerId): array
    {
        $base = self::baseBp();
        $terms = ['commission_bp' => $base, 'commission_policy' => (string) config('freeci.finance.commission_policy'), 'commission_base_bp' => null, 'commission_reason' => null];
        $grant = $this->reserve($orderId, $freelancerId, $base);
        if ($grant !== null) {
            $terms['commission_bp'] = (int) $grant->rate_bp;
            $terms['commission_base_bp'] = $base;
            $terms['commission_reason'] = $grant->source === 'campaign' ? 'Code '.$grant->code : 'Parrainage';
        }

        return $terms;
    }

    private function reserve(string $orderId, string $freelancerId, int $base): ?object
    {
        $candidates = DB::table('commission_grants as g')->leftJoin('promo_campaigns as c', 'c.id', '=', 'g.campaign_id')
            ->where('g.user_id', $freelancerId)->where('g.state', 'active')->where('g.rate_bp', '<', $base)
            ->orderBy('g.rate_bp')->orderBy('g.created_at')->orderBy('g.id')->get(['g.id', 'g.rate_bp', 'g.source', 'c.code']);
        foreach ($candidates as $c) {
            $g = DB::table('commission_grants')->where('id', $c->id)->where('state', 'active')->lockForUpdate()->first();
            if ($g === null || (int) $g->total - (int) $g->consumed - $this->reserved($g->id) <= 0) {
                continue;
            }
            DB::table('commission_grant_uses')->insert(['grant_id' => $g->id, 'order_id' => $orderId, 'state' => 'reserved', 'created_at' => now(), 'updated_at' => now()]);

            return $c;
        }

        return null;
    }

    private function reserved(string $grantId): int
    {
        return (int) DB::table('commission_grant_uses')->where('grant_id', $grantId)->where('state', 'reserved')->count();
    }

    /**
     * Commission d'une commande, pour son FREELANCE seulement (le client ne voit jamais la commission). Le taux est celui de l'accord.
     *
     * @return array{price: int, bp: int, base_bp: ?int, reason: ?string, commission: int, share: int, offered: bool, remaining: int}|null
     */
    public static function describe(string $reference, string $userId): ?array
    {
        $r = DB::table('orders')->join('order_agreements as a', 'a.order_id', '=', 'orders.id')->where('orders.reference', $reference)->where('orders.freelancer_id', $userId)
            ->first(['a.price_xof', 'a.commission_bp', 'a.commission_base_bp', 'a.commission_reason']);
        if ($r === null || $r->commission_bp === null) {
            return null;
        }
        $c = intdiv((int) $r->price_xof * (int) $r->commission_bp + 5000, 10000);

        return ['price' => (int) $r->price_xof, 'bp' => (int) $r->commission_bp, 'base_bp' => $r->commission_base_bp === null ? null : (int) $r->commission_base_bp, 'reason' => $r->commission_reason,
            'commission' => $c, 'share' => (int) $r->price_xof - $c, 'offered' => $r->commission_base_bp !== null && (int) $r->commission_bp < (int) $r->commission_base_bp, 'remaining' => self::available($userId)];
    }

    /** Commandes à commission offerte encore disponibles pour un compte (attributions actives, taux inférieur au taux normal). */
    public static function available(string $userId): int
    {
        $base = self::baseBp();
        $n = 0;
        foreach (DB::table('commission_grants')->where('user_id', $userId)->where('state', 'active')->where('rate_bp', '<', $base)->get(['id', 'total', 'consumed']) as $g) {
            $n += max(0, (int) $g->total - (int) $g->consumed - (int) DB::table('commission_grant_uses')->where('grant_id', $g->id)->where('state', 'reserved')->count());
        }

        return $n;
    }
}
