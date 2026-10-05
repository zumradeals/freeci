<?php

namespace App\Modules\Orders\Queries;

use App\Modules\Accounts\Models\User;
use App\Modules\Finance\SandboxGate;
use App\Modules\Orders\Actions\ExpireOverdueOrders;
use App\Modules\Orders\Data\OrderCard;
use App\Modules\Orders\Data\TaskItem;
use App\Modules\Orders\Enums\OrderState;
use App\Modules\Orders\Models\Order;
use App\Shared\Dates;
use App\Shared\Money;

/** Tableau de bord client : vraies données, actions classées par urgence réelle (échéance la plus proche d'abord). */
final class ClientOverview
{
    public function __construct(private ExpireOverdueOrders $expire, private ListOrders $list) {}

    /** @return array{tasks: list<TaskItem>, orders: list<OrderCard>, counts: array<string,int>} */
    public function __invoke(User $client): array
    {
        $this->expire->__invoke($client->getKey());
        $orders = Order::query()->with(['agreement', 'client', 'freelancer'])->where('client_id', $client->getKey())->get();

        $gate = app(SandboxGate::class);
        $tasks = $orders->whereIn('state', [OrderState::AwaitingPayment, OrderState::AwaitingBrief])->map(function (Order $o) use ($gate) {
            $due = $o->payment_deadline_at;
            if ($o->state === OrderState::AwaitingBrief) {
                return new TaskItem('Compléter le brief', $o->agreement->service_title.' · '.$o->reference, null, 'Le paiement est confirmé : le travail démarre dès que le brief est complet.',
                    'Aucune échéance de réalisation ne court avant le départ.', 'Voir le brief', route('orders.show', $o->reference).'#brief', 'clipboard');
            }
            $open = $o->payments()->whereIn('state', ['created', 'pending', 'unknown'])->exists();
            $confirmed = $o->payments()->where('state', 'confirmed')->exists();
            if ($open) {
                return new TaskItem('Vérification du paiement en cours', $o->agreement->service_title.' · '.$o->reference, null, 'Ne payez pas une seconde fois : le résultat est vérifié côté serveur.',
                    'Le travail commence après paiement confirmé et brief complet.', 'Voir l’état du paiement', route('orders.payment', $o->reference), 'clock', false);
            }
            if (! $confirmed && $gate->allows($o)) {
                return new TaskItem('Payer la commande (simulation)', $o->agreement->service_title.' · '.$o->reference, $due,
                    $due ? 'À payer avant le '.Dates::format($due) : 'Paiement simulé : aucun argent n’est débité.', 'Le travail commence après paiement confirmé et brief complet.',
                    'Payer '.Money::xof($o->agreement->price_xof)->formatted().' FCFA', route('orders.payment', $o->reference), 'card');
            }

            return new TaskItem('Commande en attente de paiement', $o->agreement->service_title.' · '.$o->reference, null, 'Le paiement n’est pas ouvert pour cette commande : aucune échéance de paiement ne court.',
                'Le travail commence après paiement confirmé et brief complet.', 'Voir la commande', route('orders.show', $o->reference), 'card', false);
        })->sortBy(fn (TaskItem $t) => $t->due?->getTimestamp() ?? PHP_INT_MAX)->values()->all();

        return [
            'tasks' => $tasks,
            'orders' => ($this->list)($client, 'client'),
            'counts' => [
                'En attente de réponse' => $orders->where('state', OrderState::AwaitingAcceptance)->count(),
                'En attente de paiement' => $orders->where('state', OrderState::AwaitingPayment)->count(),
                'En cours' => $orders->where('state', OrderState::InProgress)->count(),
                'Commandes au total' => $orders->count(),
            ],
        ];
    }
}
