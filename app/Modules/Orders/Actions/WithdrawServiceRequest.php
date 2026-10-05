<?php

namespace App\Modules\Orders\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Orders\Enums\ClosureReason;
use App\Modules\Orders\Enums\OrderState;
use App\Modules\Orders\Models\Order;

/** `Orders\WithdrawServiceRequest` : le client retire sa demande tant qu'elle n'a pas reçu de réponse. */
final class WithdrawServiceRequest extends ChangesOrder
{
    /** @return array{0: Order, 1: bool} */
    public function __invoke(User $client, string $reference, int $expectedVersion, string $operationKey): array
    {
        return $this->transition(
            $client, $reference, 'orders.withdraw', $operationKey, $expectedVersion,
            fn (Order $o) => $o->client_id === $client->getKey(),
            [OrderState::AwaitingAcceptance], OrderState::Cancelled, 'withdrawn', 'Demande retirée par le client.', ClosureReason::Withdrawn,
        );
    }
}
