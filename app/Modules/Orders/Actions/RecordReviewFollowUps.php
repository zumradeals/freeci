<?php

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Enums\OrderState;
use App\Modules\Orders\Models\Delivery;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Silence du client après le délai d'examen : la commande RESTE « livrée » et ouverte. On enregistre seulement un BESOIN DE SUIVI
 * (une fois par livraison) et une ligne d'historique. Aucune validation, aucune clôture, aucune libération de fonds, et aucun
 * support n'est contacté : il n'existe pas encore de mécanisme qui le fasse (la ligne d'historique le dit explicitement).
 */
final class RecordReviewFollowUps
{
    public const KIND = 'review_silence';

    /** @return int nombre de besoins de suivi enregistrés par cet appel */
    public function __invoke(?string $partyId = null): int
    {
        $ids = Delivery::query()->where('state', 'submitted')->where('review_deadline_at', '<=', now())
            ->whereHas('order', fn ($q) => $q->where('state', OrderState::Delivered->value)
                ->when($partyId, fn ($w) => $w->where(fn ($x) => $x->where('client_id', $partyId)->orWhere('freelancer_id', $partyId))))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('order_follow_ups')->whereColumn('order_follow_ups.delivery_id', 'deliveries.id')->where('kind', self::KIND))
            ->pluck('order_id')->unique();

        return $ids->filter(fn ($orderId) => $this->forOrder($orderId))->count();
    }

    public function forOrder(string $orderId): bool
    {
        return DB::transaction(function () use ($orderId) {
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->first();
            if ($order === null || $order->state !== OrderState::Delivered) {
                return false;
            }
            $latest = Delivery::query()->where('order_id', $orderId)->where('state', 'submitted')->orderByDesc('version')->first();
            if ($latest === null || $latest->review_deadline_at->isFuture()) {
                return false;
            }
            $inserted = DB::table('order_follow_ups')->insertOrIgnore(['order_id' => $orderId, 'delivery_id' => $latest->getKey(), 'kind' => self::KIND, 'recorded_at' => now()]);
            if ($inserted === 0) {
                return false;
            }
            $order->events()->create(['type' => 'review_overdue', 'actor_id' => null, 'meta' => ['delivery_version' => $latest->version],
                'note' => 'Délai d’examen dépassé. La commande reste ouverte : rien n’est validé ni clôturé automatiquement. Besoin de suivi enregistré ; aucun support n’a été contacté automatiquement.']);

            return true;
        });
    }
}
