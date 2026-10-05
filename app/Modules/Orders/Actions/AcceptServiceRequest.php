<?php

namespace App\Modules\Orders\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Orders\Enums\OrderState;
use App\Modules\Orders\Models\Order;

/** `Orders\AcceptServiceRequest` : seul le freelance de la commande, tant que la demande est ouverte et dans les délais. */
final class AcceptServiceRequest extends ChangesOrder
{
    /** @return array{0: Order, 1: bool} */
    public function __invoke(User $freelancer, string $reference, int $expectedVersion, string $operationKey): array
    {
        return $this->transition(
            $freelancer, $reference, 'orders.accept', $operationKey, $expectedVersion,
            fn (Order $o) => $o->freelancer_id === $freelancer->getKey(),
            [OrderState::AwaitingAcceptance], OrderState::AwaitingPayment, 'accepted', startsPaymentWindow: true,
        );
    }
}
