<?php

namespace App\Modules\Orders\Queries;

use App\Modules\Accounts\Models\User;
use App\Modules\Orders\Actions\ExpireOverdueOrders;
use App\Modules\Orders\Data\OrderCard;
use App\Modules\Orders\Data\TaskItem;
use App\Modules\Orders\Enums\OrderState;
use App\Modules\Orders\Models\Order;
use App\Shared\Dates;

/** Tableau de bord client : vraies données, actions classées par urgence réelle (échéance la plus proche d'abord). */
final class ClientOverview
{
    public function __construct(private ExpireOverdueOrders $expire, private ListOrders $list) {}

    /** @return array{tasks: list<TaskItem>, orders: list<OrderCard>, counts: array<string,int>} */
    public function __invoke(User $client): array
    {
        $this->expire->__invoke($client->getKey());
        $orders = Order::query()->with(['agreement', 'client', 'freelancer'])->where('client_id', $client->getKey())->get();

        $tasks = $orders->where('state', OrderState::AwaitingPayment)->map(function (Order $o) {
            $due = $o->payment_deadline_at;

            return new TaskItem(
                title: 'Régler la commande',
                object: $o->agreement->service_title.' · '.$o->reference,
                due: $due,
                dueLabel: $due ? 'À payer avant le '.Dates::format($due) : 'Le paiement n’est pas encore ouvert : aucune échéance de paiement ne court.',
                effect: 'Le travail commence après paiement confirmé et brief complet.',
                cta: 'Voir la commande',
                url: route('orders.show', $o->reference),
                icon: 'card',
                actionable: false,
            );
        })->sortBy(fn (TaskItem $t) => $t->due?->getTimestamp() ?? PHP_INT_MAX)->values()->all();

        return [
            'tasks' => $tasks,
            'orders' => ($this->list)($client, 'client'),
            'counts' => [
                'En attente de réponse' => $orders->where('state', OrderState::AwaitingAcceptance)->count(),
                'En attente de paiement' => $orders->where('state', OrderState::AwaitingPayment)->count(),
                'Commandes au total' => $orders->count(),
            ],
        ];
    }
}
