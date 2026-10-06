<?php

namespace App\Modules\Orders\Queries;

use App\Modules\Accounts\Models\User;
use App\Modules\Finance\PaymentGate;
use App\Modules\Missions\Queries\ClientMissions;
use App\Modules\Orders\Actions\ExpireOverdueOrders;
use App\Modules\Orders\Actions\RecordReviewFollowUps;
use App\Modules\Orders\Data\OrderCard;
use App\Modules\Orders\Data\TaskItem;
use App\Modules\Orders\Enums\OrderState;
use App\Modules\Orders\Models\Order;
use App\Shared\Dates;
use App\Shared\Money;

/** Tableau de bord client : vraies données, actions classées par urgence réelle (échéance la plus proche d'abord). */
final class ClientOverview
{
    public function __construct(private ExpireOverdueOrders $expire, private ListOrders $list, private RecordReviewFollowUps $followUps) {}

    /** @return array{tasks: list<TaskItem>, orders: list<OrderCard>, counts: array<string,int>} */
    public function __invoke(User $client): array
    {
        $this->expire->__invoke($client->getKey());
        $this->followUps->__invoke($client->getKey());
        $orders = Order::query()->with(['agreement', 'client', 'freelancer', 'latestDelivery', 'pendingExtension'])->where('client_id', $client->getKey())->get();

        $gate = app(PaymentGate::class);
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
                return new TaskItem('Payer la commande', $o->agreement->service_title.' · '.$o->reference, $due,
                    $due ? 'À payer avant le '.Dates::format($due) : ($o->environment === 'test' ? 'Mode test : aucun argent réel n’est débité.' : 'Paiement sécurisé par Genius Pay.'), 'Le travail commence après paiement confirmé et brief complet.',
                    'Payer '.Money::xof($o->agreement->price_xof)->formatted().' FCFA', route('orders.payment', $o->reference), 'card');
            }

            return new TaskItem('Commande en attente de paiement', $o->agreement->service_title.' · '.$o->reference, null, 'Le paiement n’est pas ouvert pour cette commande : aucune échéance de paiement ne court.',
                'Le travail commence après paiement confirmé et brief complet.', 'Voir la commande', route('orders.show', $o->reference), 'card', false);
        })->sortBy(fn (TaskItem $t) => $t->due?->getTimestamp() ?? PHP_INT_MAX)->values()->all();

        // Livraisons à examiner et reports à décider : de vraies actions, avec leur échéance réelle.
        $review = $orders->where('state', OrderState::Delivered)->filter(fn (Order $o) => $o->latestDelivery !== null)->map(function (Order $o) {
            $d = $o->latestDelivery;
            $late = $d->review_deadline_at->lte(now());

            return new TaskItem('Examiner la livraison v'.$d->version, $o->agreement->service_title.' · '.$o->reference, $d->review_deadline_at,
                ($late ? 'Délai d’examen dépassé le ' : 'À décider avant le ').Dates::format($d->review_deadline_at),
                'Vous pourrez valider la livraison ou demander une correction. Sans réponse, rien n’est validé à votre place.', 'Examiner la livraison', route('orders.show', $o->reference).'#livraison-v'.$d->version, 'inbox');
        })->values()->all();
        $extensions = $orders->filter(fn (Order $o) => $o->pendingExtension !== null && in_array($o->state, [OrderState::InProgress, OrderState::RevisionRequested], true))->map(fn (Order $o) => new TaskItem(
            'Report d’échéance à décider', $o->agreement->service_title.' · '.$o->reference, $o->due_at, 'Échéance actuelle : '.Dates::format($o->due_at),
            'L’échéance ne change que si vous acceptez.', 'Répondre au report', route('orders.show', $o->reference), 'clock'))->values()->all();
        // Missions : propositions à comparer, sélection terminée à trancher, correction demandée par la modération.
        $missionTasks = collect(app(ClientMissions::class)->list($client))->filter(fn ($m) => $m['needsAction'] || ($m['proposals'] > 0 && $m['status'] === 'Ouverte'))->map(fn ($m) => new TaskItem(
            $m['needsAction'] ? ($m['status'] === 'À corriger' ? 'Corriger votre mission' : 'Rouvrir ou fermer la mission') : $m['proposals'].' proposition'.($m['proposals'] > 1 ? 's' : '').' à examiner',
            $m['title'], null, $m['needsAction'] ? (string) $m['note'] : 'Comparez les propositions puis retenez-en une.', 'Rien n’est rouvert, retenu ou validé à votre place.',
            $m['needsAction'] ? 'Ouvrir la mission' : 'Comparer les propositions', $m['needsAction'] ? route('client.missions.show', $m['id']) : route('client.missions.proposals', $m['id']), 'briefcase'))->values()->all();
        $tasks = collect(array_merge($review, $extensions, $missionTasks, $tasks))->sortBy(fn (TaskItem $t) => $t->due?->getTimestamp() ?? PHP_INT_MAX)->values()->all();

        return [
            'tasks' => $tasks,
            'orders' => ($this->list)($client, 'client'),
            'counts' => [
                'En attente de réponse' => $orders->where('state', OrderState::AwaitingAcceptance)->count(),
                'En attente de paiement' => $orders->where('state', OrderState::AwaitingPayment)->count(),
                'En cours' => $orders->whereIn('state', [OrderState::InProgress, OrderState::RevisionRequested])->count(),
                'À examiner' => $orders->where('state', OrderState::Delivered)->count(),
                'Commandes au total' => $orders->count(),
            ],
        ];
    }
}
