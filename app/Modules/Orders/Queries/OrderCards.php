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
                : 'Paiement bientôt disponible',
            OrderState::Cancelled, OrderState::Expired => $o->closure_reason?->label() ?? '',
            default => '',
        };

        return new OrderCard(
            reference: $o->reference,
            title: $o->agreement->service_title,
            otherParty: $asFreelancer ? $o->client->name : $o->freelancer->name,
            amount: Money::xof($o->agreement->price_xof),
            stateLabel: $o->state->label($asFreelancer), tone: $tone, icon: $icon, info: $info, isDemo: $o->is_demo,
        );
    }
}
