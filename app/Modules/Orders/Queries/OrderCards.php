<?php

namespace App\Modules\Orders\Queries;

use App\Modules\Orders\Data\OrderCard;
use App\Modules\Orders\Enums\OrderState;
use App\Modules\Orders\Models\Order;
use App\Shared\Dates;
use App\Shared\Money;

/** Fabrique de lignes de liste (une seule règle d'affichage pour tous les tableaux). */
final class OrderCards
{
    public static function make(Order $o, bool $asFreelancer): OrderCard
    {
        [$tone, $icon] = $o->state->tone($asFreelancer);
        $info = match ($o->state) {
            OrderState::AwaitingAcceptance => $asFreelancer
                ? 'Répondre avant le '.Dates::short($o->response_deadline_at)
                : 'Réponse attendue avant le '.Dates::short($o->response_deadline_at),
            OrderState::AwaitingPayment => $o->payment_deadline_at
                ? 'À payer avant le '.Dates::short($o->payment_deadline_at)
                : 'Paiement non ouvert pour le moment',
            OrderState::AwaitingBrief => 'Brief à compléter avant le départ',
            OrderState::InProgress => $o->due_at ? ($o->due_at->lte(now()) ? 'Échéance dépassée depuis le ' : 'Livraison prévue le ').Dates::short($o->due_at) : '',
            OrderState::RevisionRequested => $asFreelancer ? 'Correction à traiter · livraison prévue le '.Dates::short($o->due_at) : 'Le freelance prépare une nouvelle version',
            OrderState::Delivered => $o->latestDelivery
                ? ($asFreelancer ? 'Livraison v'.$o->latestDelivery->version.' en attente d’examen' : 'À examiner : livraison v'.$o->latestDelivery->version.' · avant le '.Dates::short($o->latestDelivery->review_deadline_at))
                : '',
            OrderState::Closed => 'Clôturée le '.Dates::short($o->closed_at),
            OrderState::Cancelled, OrderState::Expired => $o->closure_reason?->label() ?? '',
            default => '',
        };

        $todo = $asFreelancer
            ? [OrderState::AwaitingAcceptance, OrderState::InProgress, OrderState::RevisionRequested]
            : [OrderState::AwaitingPayment, OrderState::AwaitingBrief, OrderState::Delivered];
        $group = $o->state->isFinal() ? 'done' : (in_array($o->state, $todo, true) ? 'todo' : 'active');

        return new OrderCard(
            reference: $o->reference,
            title: $o->agreement->service_title,
            otherParty: $asFreelancer ? $o->client->name : $o->freelancer->name,
            otherPartyId: (string) ($asFreelancer ? $o->client_id : $o->freelancer_id),
            amount: Money::xof($o->agreement->price_xof),
            stateLabel: $o->state->label($asFreelancer), tone: $tone, icon: $icon, info: $info, isDemo: $o->is_demo, environment: $o->environment, group: $group,
        );
    }
}
