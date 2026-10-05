<?php

namespace App\Modules\Orders\Queries;

use App\Modules\Accounts\Actions\PublishFreelanceProfile;
use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Models\ServiceVersion;
use App\Modules\Missions\Queries\FreelancerProposals;
use App\Modules\Orders\Actions\ExpireOverdueOrders;
use App\Modules\Orders\Actions\RecordReviewFollowUps;
use App\Modules\Orders\Data\OrderCard;
use App\Modules\Orders\Data\TaskItem;
use App\Modules\Orders\Enums\OrderState;
use App\Modules\Orders\Models\Order;
use App\Shared\Dates;
use Illuminate\Support\Str;

/** Tableau de bord freelance : demandes à traiter (échéance la plus proche d'abord), commandes en attente du client. */
final class FreelancerOverview
{
    public function __construct(private ExpireOverdueOrders $expire, private ListOrders $list, private RecordReviewFollowUps $followUps) {}

    /** @return array{tasks: list<TaskItem>, waiting: list<OrderCard>, orders: list<OrderCard>, counts: array<string,int>} */
    public function __invoke(User $freelancer): array
    {
        $this->expire->__invoke($freelancer->getKey());
        $this->followUps->__invoke($freelancer->getKey());
        $orders = Order::query()->with(['agreement', 'client', 'freelancer', 'latestDelivery', 'pendingExtension'])->where('freelancer_id', $freelancer->getKey())->get();

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

        $work = $orders->whereIn('state', [OrderState::InProgress, OrderState::RevisionRequested])->map(function (Order $o) {
            $late = $o->due_at->lte(now());
            $revision = $o->state === OrderState::RevisionRequested;

            return new TaskItem($revision ? 'Répondre à la correction demandée' : 'Livrer la commande', $o->agreement->service_title.' · '.$o->reference.' · Client : '.$o->client->name, $o->due_at,
                ($late ? 'Échéance dépassée le ' : 'Livrer avant le ').Dates::format($o->due_at),
                $revision ? 'Déposez une nouvelle version : la précédente reste conservée.' : 'Le client ne voit rien avant que vous ne soumettiez la livraison.',
                $revision ? 'Préparer la nouvelle version' : 'Préparer la livraison', route('orders.show', $o->reference), 'inbox');
        })->values()->all();
        // Catalogue : service à corriger (motif de la modération) et profil à terminer.
        $fixes = ServiceVersion::query()->where('state', 'changes_requested')
            ->whereHas('service.freelanceProfile', fn ($q) => $q->where('user_id', $freelancer->getKey()))->with('service')->get()->map(fn ($v) => new TaskItem(
                'Corriger votre service', $v->title.' · version '.$v->number, null, 'Motif de la modération : « '.Str::limit((string) $v->decision_note, 120).' »',
                'Corrigez puis soumettez de nouveau : rien n’est publié tant que la version n’est pas approuvée.', 'Corriger le service', route('freelance.services.edit', $v->service_id), 'pencil'))->values()->all();
        $profile = $freelancer->freelanceProfile;
        $profileTask = $profile !== null && $profile->published_at === null ? [new TaskItem('Terminer et publier votre profil', 'Manque : '.(implode(', ', array_map('mb_strtolower', PublishFreelanceProfile::missing($profile))) ?: 'rien, il ne reste qu’à le publier'), null,
            'Sans profil publié, vos services ne peuvent pas être soumis.', 'Votre profil public affiche vos informations publiques et vos services publiés.', 'Compléter mon profil', route('freelance.profile'), 'user')] : [];
        $stale = collect(app(FreelancerProposals::class)->list($freelancer))->filter(fn ($p) => $p['stale'] && $p['missionOpen'])->map(fn ($p) => new TaskItem(
            'Reconfirmer votre proposition', $p['missionTitle'].' · version '.$p['number'], null, 'Le besoin a été modifié depuis votre proposition.', 'Sans reconfirmation, le client ne peut pas la retenir.', 'Reconfirmer', route('missions.proposal', $p['missionSlug']), 'pencil'))->values()->all();
        $tasks = collect(array_merge($work, $fixes, $stale, $profileTask, $tasks))->sortBy(fn (TaskItem $t) => $t->due?->getTimestamp() ?? PHP_INT_MAX)->values()->all();

        $waitingStates = [OrderState::AwaitingPayment, OrderState::AwaitingBrief, OrderState::Delivered];

        return [
            'tasks' => $tasks,
            'waiting' => $orders->filter(fn (Order $o) => in_array($o->state, $waitingStates, true) || ($o->pendingExtension !== null && in_array($o->state, [OrderState::InProgress, OrderState::RevisionRequested], true)))->map(fn (Order $o) => OrderCards::make($o, true))->values()->all(),
            'orders' => ($this->list)($freelancer, 'freelancer'),
            'counts' => [
                'Demandes à accepter' => $orders->where('state', OrderState::AwaitingAcceptance)->count(),
                'En attente de paiement' => $orders->where('state', OrderState::AwaitingPayment)->count(),
                'En cours' => $orders->whereIn('state', [OrderState::InProgress, OrderState::RevisionRequested])->count(),
                'Livrées' => $orders->where('state', OrderState::Delivered)->count(),
                'Commandes au total' => $orders->count(),
            ],
        ];
    }
}
