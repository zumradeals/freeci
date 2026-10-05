<?php

namespace App\Modules\Orders\Queries;

use App\Modules\Accounts\Models\User;
use App\Modules\Orders\Actions\ExpireOverdueOrders;
use App\Modules\Orders\Data\OrderCard;
use App\Modules\Orders\Data\TaskItem;
use App\Modules\Orders\Enums\OrderState;
use App\Modules\Orders\Models\Order;
use App\Shared\Dates;

/** Tableau de bord freelance : demandes à traiter (échéance la plus proche d'abord), commandes en attente du client. */
final class FreelancerOverview
{
    public function __construct(private ExpireOverdueOrders $expire, private ListOrders $list) {}

    /** @return array{tasks: list<TaskItem>, waiting: list<OrderCard>, orders: list<OrderCard>, counts: array<string,int>} */
    public function __invoke(User $freelancer): array
    {
        $this->expire->__invoke($freelancer->getKey());
        $orders = Order::query()->with(['agreement', 'client', 'freelancer'])->where('freelancer_id', $freelancer->getKey())->get();

        $tasks = $orders->where('state', OrderState::AwaitingAcceptance)->sortBy('response_deadline_at')->map(fn (Order $o) => new TaskItem(
            title: 'Répondre à la demande',
            object: $o->agreement->service_title.' · '.$o->reference.' · Client : '.$o->client->name,
            due: $o->response_deadline_at,
            dueLabel: 'Répondre avant le '.Dates::format($o->response_deadline_at),
            effect: 'Si vous acceptez, la commande attend le paiement du client. Le travail ne commence qu’après paiement confirmé et brief complet.',
            cta: 'Répondre à la demande',
            url: route('orders.show', $o->reference),
            icon: 'inbox',
        ))->values()->all();

        return [
            'tasks' => $tasks,
            'waiting' => $orders->whereIn('state', [OrderState::AwaitingPayment, OrderState::AwaitingBrief])->map(fn (Order $o) => OrderCards::make($o, true))->values()->all(),
            'orders' => ($this->list)($freelancer, 'freelancer'),
            'counts' => [
                'Demandes à accepter' => $orders->where('state', OrderState::AwaitingAcceptance)->count(),
                'En attente de paiement' => $orders->where('state', OrderState::AwaitingPayment)->count(),
                'En cours' => $orders->where('state', OrderState::InProgress)->count(),
                'Commandes au total' => $orders->count(),
            ],
        ];
    }
}
