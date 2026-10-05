<?php

namespace App\Modules\Orders\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Orders\Enums\OrderState;
use App\Modules\Orders\Exceptions\InvalidTransition;
use App\Modules\Orders\Exceptions\OrderForbidden;
use App\Modules\Orders\Exceptions\RuleViolation;
use App\Modules\Orders\Models\CorrectionRequest;
use App\Modules\Orders\Models\Delivery;
use App\Modules\Orders\Models\Order;
use App\Shared\CommandReceipts;
use Illuminate\Support\Facades\DB;

/**
 * Le client dont les corrections incluses sont épuisées n'est jamais forcé de valider : il peut laisser la livraison non validée.
 * S'il est en désaccord, il le signale ici : la commande reste « livrée » et ouverte, un BESOIN DE SUIVI est enregistré (une fois
 * par livraison) avec son message. Ce n'est pas un litige et aucun support n'est contacté : aucun dispositif ne le fait encore.
 */
final class SignalDisagreement
{
    public const KIND = 'client_disagreement';

    public const NOTE_MIN = 15;

    public const NOTE_MAX = 2000;

    /** @return array{0: Order, 1: bool} */
    public function __invoke(User $client, string $reference, string $deliveryId, string $note, int $expectedVersion, string $operationKey): array
    {
        $note = trim($note);
        if (mb_strlen($note) < self::NOTE_MIN || mb_strlen($note) > self::NOTE_MAX) {
            throw new RuleViolation('Expliquez votre désaccord ('.self::NOTE_MIN.' à '.self::NOTE_MAX.' caractères).');
        }
        $order = Order::query()->where('reference', $reference)->first();
        if ($order === null || $order->client_id !== $client->getKey()) {
            throw new OrderForbidden;
        }

        [$id, $replayed] = CommandReceipts::once($client->getKey(), 'orders.signal_disagreement', $operationKey, ['reference' => $reference, 'version' => $expectedVersion, 'delivery' => $deliveryId, 'note' => $note],
            function () use ($order, $client, $deliveryId, $note, $expectedVersion) {
                $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
                $latest = Delivery::query()->where('order_id', $locked->getKey())->where('state', 'submitted')->orderByDesc('version')->first();
                if ($locked->client_id !== $client->getKey()) {
                    throw new OrderForbidden;
                }
                if ($locked->row_version !== $expectedVersion || $locked->state !== OrderState::Delivered || $latest === null || $latest->getKey() !== $deliveryId) {
                    throw new InvalidTransition;
                }
                $included = (int) $locked->agreement->revisions_included;
                if (CorrectionRequest::query()->where('order_id', $locked->getKey())->count() < $included) {
                    throw new RuleViolation('Il vous reste des corrections incluses : demandez plutôt une correction.');
                }
                $inserted = DB::table('order_follow_ups')->insertOrIgnore(['order_id' => $locked->getKey(), 'delivery_id' => $latest->getKey(), 'kind' => self::KIND, 'note' => $note, 'actor_id' => $client->getKey(), 'recorded_at' => now()]);
                if ($inserted === 0) {
                    throw new RuleViolation('Votre désaccord est déjà enregistré pour cette livraison.');
                }
                $locked->events()->create(['type' => 'disagreement_reported', 'actor_id' => $client->getKey(), 'note' => $note, 'meta' => ['delivery_version' => $latest->version]]);

                return $locked->getKey();
            });

        return [Order::findOrFail($id), $replayed];
    }
}
