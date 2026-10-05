<?php

namespace App\Modules\Orders\Actions;

use App\Modules\Orders\Enums\ClosureReason;
use App\Modules\Orders\Enums\OrderState;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Expire les demandes dont le délai de réponse est dépassé (et, une fois le paiement ouvert, les paiements en retard).
 * Appelée avant toute action sur une commande, à l'affichage des listes d'un utilisateur et par la tâche planifiée :
 * l'exactitude ne dépend donc pas de la planification.
 */
class ExpireOverdueOrders
{
    /** @return int nombre de commandes expirées */
    public function __invoke(?string $partyId = null): int
    {
        $ids = Order::query()
            ->when($partyId, fn ($q) => $q->where(fn ($w) => $w->where('client_id', $partyId)->orWhere('freelancer_id', $partyId)))
            ->where(function ($q) {
                $q->where(fn ($w) => $w->where('state', OrderState::AwaitingAcceptance->value)->where('response_deadline_at', '<=', now()));
                // Échéance de paiement : seulement pour les commandes dont le paiement a été OUVERT (échéance enregistrée).
                $q->orWhere(fn ($w) => $w->where('state', OrderState::AwaitingPayment->value)->whereNotNull('payment_deadline_at')->where('payment_deadline_at', '<=', now()));
            })->pluck('id');

        return $ids->filter(fn ($id) => $this->forOrder($id))->count();
    }

    /** @return bool vrai si cette commande a été expirée par cet appel */
    public function forOrder(string $orderId): bool
    {
        return DB::transaction(function () use ($orderId) {
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->first();
            if ($order === null) {
                return false;
            }
            $reason = null;
            if ($order->state === OrderState::AwaitingAcceptance && $order->response_deadline_at->lte(now())) {
                $reason = ClosureReason::ExpiredAcceptance;
            } elseif ($order->state === OrderState::AwaitingPayment && $order->payment_deadline_at !== null && $order->payment_deadline_at->lte(now())
                && ! $order->payments()->whereIn('state', ['created', 'pending', 'unknown', 'confirmed'])->exists()) {
                // Jamais d'expiration pendant qu'un paiement est en cours, incertain ou confirmé (docs/02 §4.4).
                $reason = ClosureReason::ExpiredPayment;
            }
            if ($reason === null) {
                return false;
            }
            $from = $order->state;
            $order->forceFill(['state' => OrderState::Expired, 'closure_reason' => $reason, 'closed_at' => now(), 'row_version' => $order->row_version + 1])->save();
            $order->events()->create(['type' => 'expired', 'actor_id' => null, 'from_state' => $from->value, 'to_state' => 'expired', 'note' => $reason->label()]);

            return true;
        });
    }
}
