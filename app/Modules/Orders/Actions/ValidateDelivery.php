<?php

namespace App\Modules\Orders\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Missions\Actions\MissionPlans;
use App\Modules\Orders\Enums\ClosureReason;
use App\Modules\Orders\Enums\OrderState;
use App\Modules\Orders\Exceptions\InvalidTransition;
use App\Modules\Orders\Exceptions\OrderForbidden;
use App\Modules\Orders\Models\Delivery;
use App\Modules\Orders\Models\Order;
use App\Shared\CommandReceipts;

/**
 * `Orders\ValidateDelivery` : validation EXPLICITE du client → « validée » puis « clôturée » (clôture commerciale).
 * Ne touche ni au paiement, ni au registre : aucun reversement n'est confirmé ni déclenché par cette action.
 * Jamais appelée sur silence : le délai d'examen n'a aucun effet automatique.
 */
final class ValidateDelivery
{
    /** @return array{0: Order, 1: bool} */
    public function __invoke(User $client, string $reference, string $deliveryId, int $expectedVersion, string $operationKey): array
    {
        $order = Order::query()->where('reference', $reference)->first();
        if ($order === null || $order->client_id !== $client->getKey()) {
            throw new OrderForbidden;
        }

        [$id, $replayed] = CommandReceipts::once(
            $client->getKey(), 'orders.validate_delivery', $operationKey, ['reference' => $reference, 'version' => $expectedVersion, 'delivery' => $deliveryId],
            function () use ($order, $client, $deliveryId, $expectedVersion) {
                $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
                if ($locked->client_id !== $client->getKey()) {
                    throw new OrderForbidden;
                }
                $latest = Delivery::query()->where('order_id', $locked->getKey())->where('state', 'submitted')->orderByDesc('version')->first();
                if ($locked->row_version !== $expectedVersion || $locked->state !== OrderState::Delivered
                    || ! $locked->state->canTransitionTo(OrderState::Validated) || $latest === null || $latest->getKey() !== $deliveryId) {
                    throw new InvalidTransition;
                }

                $now = now();
                $locked->events()->create(['type' => 'validated', 'actor_id' => $client->getKey(), 'from_state' => OrderState::Delivered->value, 'to_state' => OrderState::Validated->value,
                    'meta' => ['delivery_version' => $latest->version]]);
                $locked->forceFill([
                    'state' => OrderState::Closed, 'validated_delivery_id' => $latest->getKey(), 'validated_at' => $now, 'closure_reason' => ClosureReason::Validated,
                    'closed_at' => $now, 'row_version' => $locked->row_version + 1, 'updated_at' => $now,
                ])->save();
                $locked->events()->create(['type' => 'closed', 'actor_id' => null, 'from_state' => OrderState::Validated->value, 'to_state' => OrderState::Closed->value,
                    'note' => 'Clôture commerciale. Aucun reversement n’est déclenché ni confirmé par cette étape.']);
                app(MissionPlans::class)->onValidated($locked->getKey());      // jalon : ouvre le suivant ou termine le plan

                return $locked->getKey();
            },
        );

        return [Order::findOrFail($id), $replayed];
    }
}
