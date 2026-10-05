<?php

namespace App\Modules\Orders\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Orders\Enums\ClosureReason;
use App\Modules\Orders\Enums\OrderState;
use App\Modules\Orders\Models\Order;
use Illuminate\Validation\ValidationException;

/** `Orders\DeclineServiceRequest` : refus motivé (le motif est obligatoire et visible des deux parties). */
final class DeclineServiceRequest extends ChangesOrder
{
    /** @return array{0: Order, 1: bool} */
    public function __invoke(User $freelancer, string $reference, string $reason, int $expectedVersion, string $operationKey): array
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 1000) {
            throw ValidationException::withMessages(['reason' => 'Indiquez un motif de 10 à 1 000 caractères : le client le verra.']);
        }

        return $this->transition(
            $freelancer, $reference, 'orders.decline', $operationKey, $expectedVersion,
            fn (Order $o) => $o->freelancer_id === $freelancer->getKey(),
            [OrderState::AwaitingAcceptance], OrderState::Cancelled, 'declined', $reason, ClosureReason::Declined,
        );
    }
}
