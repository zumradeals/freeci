<?php

namespace App\Modules\Orders\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Orders\Enums\ClosureReason;
use App\Modules\Orders\Enums\OrderState;
use App\Modules\Orders\Exceptions\InvalidTransition;
use App\Modules\Orders\Models\Order;

/** `Orders\CancelBeforePayment` : le client annule une commande acceptée avant tout paiement (aucun montant encaissé). */
final class CancelBeforePayment extends ChangesOrder
{
    /** @return array{0: Order, 1: bool} */
    public function __invoke(User $client, string $reference, int $expectedVersion, string $operationKey): array
    {
        return $this->transition(
            $client, $reference, 'orders.cancel_before_payment', $operationKey, $expectedVersion,
            fn (Order $o) => $o->client_id === $client->getKey(),
            [OrderState::AwaitingPayment], OrderState::Cancelled, 'cancelled', 'Commande annulée par le client avant paiement.', ClosureReason::CancelledBeforePayment,
            guard: function (Order $locked): void {
                // Une tentative ouverte (paiement possiblement en cours) ou confirmée interdit l'annulation simple (remboursement : lot ultérieur).
                if ($locked->payments()->whereIn('state', ['created', 'pending', 'unknown', 'confirmed'])->exists()) {
                    throw new InvalidTransition;
                }
            },
        );
    }
}
