<?php

namespace App\Modules\Orders\Queries;

use App\Modules\Accounts\Models\User;
use App\Modules\Orders\Actions\ExpireOverdueOrders;
use App\Modules\Orders\Data\OrderDossier;
use App\Modules\Orders\Enums\OrderState;
use App\Modules\Orders\Exceptions\OrderForbidden;
use App\Modules\Orders\Models\Order;
use App\Shared\Dates;
use App\Shared\Money;

/** `Orders\GetOrderDossier` : lecture BORNÉE AUX PARTIES. Inexistant et interdit répondent pareil (docs/04 §8.5). */
final class GetOrderDossier
{
    public function __construct(private ExpireOverdueOrders $expire) {}

    public function __invoke(User $viewer, string $reference): OrderDossier
    {
        $order = Order::query()->where('reference', $reference)
            ->where(fn ($q) => $q->where('client_id', $viewer->getKey())->orWhere('freelancer_id', $viewer->getKey()))
            ->first();
        if ($order === null) {
            throw new OrderForbidden;
        }
        $this->expire->forOrder($order->getKey());
        $order = Order::with(['agreement', 'brief', 'events.actor', 'client', 'freelancer'])->findOrFail($order->getKey());

        $isFreelancer = $order->freelancer_id === $viewer->getKey();
        $perspective = $isFreelancer ? 'freelancer' : 'client';
        $a = $order->agreement;
        [$tone, $icon] = $order->state->tone($isFreelancer);

        $items = collect($order->brief->answers);
        $missing = $items->filter(fn ($i) => trim((string) ($i['answer'] ?? '')) === '')->count();

        $names = [$order->client_id => $order->client->name, $order->freelancer_id => $order->freelancer->name];
        $events = $order->events->map(function ($e) use ($names, $isFreelancer) {
            $from = $e->from_state ? OrderState::from($e->from_state) : null;
            $to = $e->to_state ? OrderState::from($e->to_state) : null;
            $who = $e->actor_id ? ($names[$e->actor_id] ?? 'Utilisateur') : 'FreeCI';
            $title = match ($e->type) {
                'requested' => "Demande envoyée par {$who}",
                'accepted' => "Demande acceptée par {$who}",
                'declined' => "Demande refusée par {$who}",
                'withdrawn' => "Demande retirée par {$who}",
                'cancelled' => "Commande annulée par {$who}",
                'expired' => 'Délai dépassé : demande expirée',
                default => $e->type,
            };

            return [
                'title' => $title, 'when' => Dates::format($e->occurred_at),
                'from' => $from?->label($isFreelancer), 'to' => $to?->label($isFreelancer), 'toTone' => $to?->tone($isFreelancer)[0],
                'note' => $e->note && $e->type !== 'withdrawn' ? $e->note : null, 'now' => false,
            ];
        })->reverse()->values()->all();
        if ($events !== []) {
            $events[0]['now'] = true;
        }

        $actions = [];
        if ($order->state === OrderState::AwaitingAcceptance) {
            $actions = $isFreelancer
                ? [['kind' => 'accept', 'label' => 'Accepter la demande', 'primary' => true], ['kind' => 'decline', 'label' => 'Refuser la demande', 'primary' => false]]
                : [['kind' => 'withdraw', 'label' => 'Retirer la demande', 'primary' => false]];
        } elseif ($order->state === OrderState::AwaitingPayment && ! $isFreelancer) {
            $actions = [['kind' => 'cancel', 'label' => 'Annuler la commande', 'primary' => false]];
        }

        return new OrderDossier(
            reference: $order->reference, version: $order->row_version, perspective: $perspective,
            stateValue: $order->state->value, stateLabel: $order->state->label($isFreelancer), tone: $tone, icon: $icon,
            isDemo: $order->is_demo, title: $a->service_title, categoryName: $a->category_name,
            otherPartyLabel: $isFreelancer ? 'Client' : 'Freelance', otherPartyName: $isFreelancer ? $order->client->name : $order->freelancer->name,
            clientName: $order->client->name, freelancerName: $order->freelancer->name,
            price: Money::xof($a->price_xof), deliveryDays: $a->delivery_days, revisionsIncluded: $a->revisions_included,
            scope: $a->scope, deliverables: $a->deliverables, exclusions: $a->exclusions,
            serviceVersion: $a->service_row_version, conditionsVersion: $a->conditions_version, conditionsAcceptedAt: $a->conditions_accepted_at,
            requestedAt: $order->requested_at, responseDeadline: $order->response_deadline_at, acceptedAt: $order->accepted_at,
            paymentDeadline: $order->payment_deadline_at, closureReason: $order->closure_reason?->label(), closureNote: $order->closure_note,
            briefItems: $items->all(), briefNotes: $order->brief->notes, briefComplete: $missing === 0, briefMissing: $missing,
            events: $events, actions: $actions,
            stepIndex: match ($order->state) {
                OrderState::AwaitingAcceptance => 0, OrderState::AwaitingPayment => 1, default => 0
            },
            isFinal: $order->state->isFinal(),
        );
    }
}
