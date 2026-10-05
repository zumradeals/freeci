<?php

namespace App\Modules\Missions\Actions;

use App\Modules\Missions\Models\Mission;
use App\Modules\Missions\Models\MissionVersion;
use App\Modules\Missions\Models\Proposal;
use App\Modules\Missions\Support\MissionHistory;
use App\Modules\Orders\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Conséquences sur la mission des événements de la commande qui en est issue, et expiration des missions sans choix.
 * - paiement CONFIRMÉ côté serveur → mission « attribuée » (jamais avant) ;
 * - commande annulée ou expirée AVANT paiement → mission « sélection terminée » : la proposition retenue est libérée, la mission n'est PAS rouverte
 *   automatiquement ; le client choisit explicitement de la rouvrir ou de la fermer (MissionAuthoring::reopen / close).
 */
final class MissionLifecycle
{
    public static function selectionEnd(MissionVersion $live): CarbonInterface
    {
        return $live->application_deadline->copy()->addDays((int) config('freeci.missions.selection_days'));
    }

    /** À appeler DANS la transaction qui vient de passer la commande en « annulée » ou « expirée ». */
    public function onOrderEnded(Order $order, string $reason): void
    {
        $m = Mission::query()->whereKey($order->mission_id)->lockForUpdate()->first();
        if ($m === null || $m->status !== 'reserved') {
            return;
        }
        $proposalId = DB::table('proposal_versions')->where('id', $order->proposal_version_id)->value('proposal_id');
        Proposal::query()->whereKey($proposalId)->where('state', 'selected')->update(['state' => 'released', 'updated_at' => now()]);
        $m->forceFill(['status' => 'selection_ended', 'selected_proposal_version_id' => null, 'row_version' => $m->row_version + 1])->save();
        MissionHistory::log($m->getKey(), 'reservation_ended', null, 'FreeCI', $reason, ['order' => $order->reference], null, $proposalId);
    }

    /** À appeler dans la transaction de ConfirmPayment, commande verrouillée. */
    public function onPaymentConfirmed(Order $order): void
    {
        $m = Mission::query()->whereKey($order->mission_id)->lockForUpdate()->first();
        if ($m === null || $m->status !== 'reserved') {
            return;
        }
        Proposal::query()->where('mission_id', $m->getKey())->where('state', 'active')->update(['state' => 'closed', 'updated_at' => now()]);
        $m->forceFill(['status' => 'awarded', 'row_version' => $m->row_version + 1])->save();
        MissionHistory::log($m->getKey(), 'awarded', null, 'FreeCI', 'Paiement confirmé côté serveur : mission attribuée.', ['order' => $order->reference]);
    }

    /** Expire les missions « ouvertes » ou « sélection terminée » dont la période de sélection est dépassée. @return int nombre de missions expirées */
    public function expireOverdue(?string $clientId = null): int
    {
        $ids = Mission::query()->whereIn('status', ['open', 'selection_ended'])->when($clientId, fn ($q) => $q->where('client_id', $clientId))
            ->with('publishedVersion')->get()->filter(fn (Mission $m) => $m->publishedVersion !== null && self::selectionEnd($m->publishedVersion)->lte(now()))->pluck('id');

        return $ids->filter(fn ($id) => DB::transaction(function () use ($id) {
            $m = Mission::query()->whereKey($id)->lockForUpdate()->first();
            if ($m === null || ! in_array($m->status, ['open', 'selection_ended'], true) || self::selectionEnd($m->publishedVersion)->gt(now())) {
                return false;
            }
            Proposal::query()->where('mission_id', $m->getKey())->where('state', 'active')->update(['state' => 'closed', 'updated_at' => now()]);
            $m->forceFill(['status' => 'expired', 'closed_at' => now(), 'row_version' => $m->row_version + 1])->save();
            MissionHistory::log($m->getKey(), 'expired', null, 'FreeCI', 'Aucune proposition retenue avant la fin de la période de sélection.');

            return true;
        }))->count();
    }
}
