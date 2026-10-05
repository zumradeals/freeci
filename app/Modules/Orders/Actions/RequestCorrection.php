<?php

namespace App\Modules\Orders\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Orders\Enums\OrderState;
use App\Modules\Orders\Exceptions\InvalidTransition;
use App\Modules\Orders\Exceptions\RuleViolation;
use App\Modules\Orders\Models\CorrectionRequest;
use App\Modules\Orders\Models\Delivery;
use App\Modules\Orders\Models\Order;

/**
 * `Orders\RequestCorrection` : le client demande une correction motivée de la DERNIÈRE version, dans la limite de l'accord figé.
 * Idempotent (clé d'opération) ; la base interdit en plus deux demandes pour une même version et un numéro en double.
 */
final class RequestCorrection extends ChangesOrder
{
    public const REASON_MIN = 15;

    public const REASON_MAX = 2000;

    /** @return array{0: Order, 1: bool} */
    public function __invoke(User $client, string $reference, string $deliveryId, string $reason, int $expectedVersion, string $operationKey): array
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < self::REASON_MIN || mb_strlen($reason) > self::REASON_MAX) {
            throw new RuleViolation('Décrivez précisément la correction demandée ('.self::REASON_MIN.' à '.self::REASON_MAX.' caractères).');
        }

        return $this->transition(
            $client, $reference, 'orders.request_correction', $operationKey, $expectedVersion,
            fn (Order $o) => $o->client_id === $client->getKey(),
            [OrderState::Delivered], OrderState::RevisionRequested, 'correction_requested', $reason,
            guard: function (Order $locked, User $actor) use ($deliveryId, $reason): array {
                $latest = Delivery::query()->where('order_id', $locked->getKey())->where('state', 'submitted')->orderByDesc('version')->first();
                if ($latest === null || $latest->getKey() !== $deliveryId) {
                    throw new InvalidTransition;                       // version périmée : une autre version a été déposée
                }
                $included = (int) $locked->agreement->revisions_included;
                $used = CorrectionRequest::query()->where('order_id', $locked->getKey())->count();
                if ($used >= $included) {
                    throw new RuleViolation('Les corrections incluses dans l’accord sont toutes utilisées ('.$used.' sur '.$included.'). Vous pouvez valider la livraison.');
                }
                $c = CorrectionRequest::create(['order_id' => $locked->getKey(), 'delivery_id' => $latest->getKey(), 'number' => $used + 1, 'reason' => $reason, 'requested_by' => $actor->getKey()]);

                return ['delivery_version' => $latest->version, 'correction_number' => $c->number, 'corrections_included' => $included];
            },
        );
    }
}
