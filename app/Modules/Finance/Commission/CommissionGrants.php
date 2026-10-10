<?php

namespace App\Modules\Finance\Commission;

use App\Modules\Accounts\Models\User;
use App\Modules\Admin\Actions\AdminAudit;
use App\Modules\Missions\Exceptions\MissionConflict;
use App\Modules\Notifications\Actions\Notify;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Attributions de commission offerte (F-14) : création, consommation au paiement, restitution avant paiement, révocation par un administrateur. */
final class CommissionGrants
{
    public function __construct(private AdminAudit $audit, private Notify $notify) {}

    /** @param  array{referral_id?: ?string, campaign_id?: ?string}  $origin */
    public function create(string $userId, string $source, array $origin, int $rateBp, int $total): string
    {
        $id = (string) Str::uuid();
        DB::table('commission_grants')->insert(['id' => $id, 'user_id' => $userId, 'source' => $source, 'referral_id' => $origin['referral_id'] ?? null, 'campaign_id' => $origin['campaign_id'] ?? null,
            'rate_bp' => $rateBp, 'total' => $total, 'consumed' => 0, 'state' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    /** Paiement confirmé : l'unité réservée est consommée. Idempotent. À appeler dans la transaction de confirmation. */
    public function onPaid(string $orderId): void
    {
        $u = DB::table('commission_grant_uses')->where('order_id', $orderId)->where('state', 'reserved')->lockForUpdate()->first();
        if ($u === null) {
            return;
        }
        DB::table('commission_grants')->where('id', $u->grant_id)->lockForUpdate()->first();
        DB::table('commission_grant_uses')->where('id', $u->id)->update(['state' => 'consumed', 'updated_at' => now()]);
        DB::table('commission_grants')->where('id', $u->grant_id)->increment('consumed', 1, ['updated_at' => now()]);
    }

    /** Commande expirée ou annulée AVANT paiement : l'unité réservée est rendue. Après paiement, rien ne change (le remboursement suit l'accord). Idempotent. */
    public function onEnded(string $orderId): void
    {
        DB::table('commission_grant_uses')->where('order_id', $orderId)->where('state', 'reserved')->update(['state' => 'returned', 'updated_at' => now()]);
    }

    public function revoke(User $admin, string $grantId, string $reason): void
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 1000) {
            throw new MissionConflict('Le motif est obligatoire (10 à 1000 caractères).');
        }
        $label = null;
        $this->audit->run($admin, 'grant.revoke', 'commission_grant', $grantId, $label, $reason, function () use ($admin, $grantId, $reason) {
            DB::transaction(function () use ($admin, $grantId, $reason) {
                $g = DB::table('commission_grants')->where('id', $grantId)->lockForUpdate()->first() ?? throw new MissionConflict('Attribution introuvable.');
                if ($g->state !== 'active') {
                    throw new MissionConflict('Cette attribution est déjà révoquée.');
                }
                DB::table('commission_grants')->where('id', $grantId)->update(['state' => 'revoked', 'revoked_reason' => $reason, 'revoked_by' => $admin->getKey(), 'revoked_at' => now(), 'updated_at' => now()]);
                ($this->notify)((string) $g->user_id, 'commission_grant_revoked', 'grant_revoked:'.$grantId, 'Commission offerte retirée', 'Motif : '.mb_substr($reason, 0, 300), 'account.referral');
            });
        });
    }
}
