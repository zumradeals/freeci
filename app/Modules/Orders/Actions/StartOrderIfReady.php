<?php

namespace App\Modules\Orders\Actions;

use App\Modules\Finance\Enums\PaymentState;
use App\Modules\Orders\Enums\OrderState;
use App\Modules\Orders\Models\Order;

/**
 * Seul chemin vers « en attente du brief » et « en cours ». À appeler DANS une transaction, commande verrouillée.
 * Conditions : paiement CONFIRMÉ côté serveur (au moins une ligne `confirmed`) ; démarrage seulement si le brief est complet.
 * Départ et échéance sont enregistrés UNE seule fois (colonnes immuables : déclencheur de base + garde ci-dessous).
 */
final class StartOrderIfReady
{
    /** @return string 'started' | 'awaiting_brief' | 'unchanged' */
    public function __invoke(Order $order): string
    {
        $order->refresh();
        if ($order->started_at !== null || ! in_array($order->state, [OrderState::AwaitingPayment, OrderState::AwaitingBrief], true)) {
            return 'unchanged';
        }
        if (! $order->payments()->where('state', PaymentState::Confirmed->value)->exists()) {
            return 'unchanged';                       // sans paiement confirmé, rien ne démarre
        }

        $from = $order->state;
        $brief = BriefStatus::of($order);
        if (! $brief['complete']) {
            if ($from === OrderState::AwaitingBrief) {
                return 'unchanged';
            }
            $order->forceFill(['state' => OrderState::AwaitingBrief, 'row_version' => $order->row_version + 1])->save();
            $order->events()->create(['type' => 'brief_awaited', 'actor_id' => null, 'from_state' => $from->value, 'to_state' => OrderState::AwaitingBrief->value,
                'note' => $brief['missing'].' élément(s) à fournir avant le départ.']);

            return 'awaiting_brief';
        }

        $now = now();
        $due = $now->copy()->addDays($order->agreement->delivery_days);
        $order->forceFill(['state' => OrderState::InProgress, 'started_at' => $now, 'due_at' => $due, 'row_version' => $order->row_version + 1])->save();
        $order->events()->create(['type' => 'work_started', 'actor_id' => null, 'from_state' => $from->value, 'to_state' => OrderState::InProgress->value,
            'meta' => ['started_at' => $now->toIso8601String(), 'due_at' => $due->toIso8601String()]]);

        return 'started';
    }
}
