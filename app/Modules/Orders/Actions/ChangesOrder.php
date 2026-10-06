<?php

namespace App\Modules\Orders\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Finance\PaymentGate;
use App\Modules\Missions\Actions\MissionLifecycle;
use App\Modules\Orders\Enums\ClosureReason;
use App\Modules\Orders\Enums\OrderState;
use App\Modules\Orders\Exceptions\InvalidTransition;
use App\Modules\Orders\Exceptions\OrderExpired;
use App\Modules\Orders\Exceptions\OrderForbidden;
use App\Modules\Orders\Models\Order;
use App\Shared\CommandReceipts;

/**
 * Gabarit des transitions de commande : autorisation, expiration, verrou de ligne, version attendue,
 * transition permise par la machine d'états, historique, idempotence — le tout côté serveur.
 */
abstract class ChangesOrder
{
    public function __construct(protected ExpireOverdueOrders $expire) {}

    /**
     * @param  callable(Order): bool  $isActor  qui a le droit d'accomplir cette action sur la commande
     * @param  list<OrderState>  $from  états de départ admis pour CETTE action
     */
    protected function transition(
        User $actor, string $reference, string $action, string $operationKey, int $expectedVersion,
        callable $isActor, array $from, OrderState $to, string $eventType, ?string $note = null,
        ?ClosureReason $reason = null, bool $startsPaymentWindow = false, ?callable $guard = null,
    ): array {
        // Un dossier inexistant et un dossier d'autrui répondent pareil : rien n'est révélé.
        $order = Order::query()->where('reference', $reference)->first();
        if ($order === null || ! $isActor($order)) {
            throw new OrderForbidden;
        }

        // Une demande dont le délai est dépassé est expirée AVANT toute action (transaction séparée, donc conservée).
        if ($this->expire->forOrder($order->getKey()) && Order::find($order->getKey())->state->value === 'expired') {
            throw new OrderExpired;
        }

        [$id, $replayed] = CommandReceipts::once(
            $actor->getKey(), $action, $operationKey, ['reference' => $reference, 'version' => $expectedVersion, 'note' => $note],
            function () use ($order, $actor, $isActor, $expectedVersion, $from, $to, $eventType, $note, $reason, $startsPaymentWindow, $guard) {
                $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
                if (! $isActor($locked)) {
                    throw new OrderForbidden;
                }
                if ($locked->row_version !== $expectedVersion || ! in_array($locked->state, $from, true) || ! $locked->state->canTransitionTo($to)) {
                    throw new InvalidTransition;
                }

                // La garde contrôle les règles métier ET peut écrire les effets liés (même transaction) ; un tableau retourné devient `meta`.
                $guardMeta = $guard !== null ? $guard($locked, $actor) : null;

                $from = $locked->state;
                $now = now();
                $updates = ['state' => $to, 'row_version' => $locked->row_version + 1, 'updated_at' => $now];
                if ($to->isFinal()) {
                    $updates += ['closure_reason' => $reason, 'closure_note' => $note, 'closed_at' => $now];
                }
                if ($startsPaymentWindow) {
                    $updates['accepted_at'] = $now;
                    // L'échéance de paiement ne court QUE si le paiement est ouvert pour CETTE commande (paiements ouverts et environnement concordant).
                    // Sinon (commande réelle, paiement jamais ouvert) : aucune échéance, donc aucune expiration pour non-paiement.
                    $paymentOpen = app(PaymentGate::class)->allows($locked);
                    $updates['payment_deadline_at'] = $paymentOpen ? $now->copy()->addHours($locked->agreement->payment_hours) : null;
                }
                $locked->forceFill($updates)->save();
                if ($locked->mission_id !== null && in_array($to, [OrderState::Cancelled, OrderState::Expired], true)) {
                    app(MissionLifecycle::class)->onOrderEnded($locked, 'Commande annulée avant paiement : la proposition retenue est libérée.');
                }
                $locked->events()->create([
                    'type' => $eventType, 'actor_id' => $actor->getKey(), 'from_state' => $from->value, 'to_state' => $to->value,
                    'note' => $note, 'meta' => $startsPaymentWindow ? ['payment_open' => $paymentOpen ?? false] : (is_array($guardMeta) ? $guardMeta : null),
                ]);

                return $locked->getKey();
            },
        );

        return [Order::findOrFail($id), $replayed];
    }
}
