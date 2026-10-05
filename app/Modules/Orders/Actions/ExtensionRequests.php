<?php

namespace App\Modules\Orders\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Orders\Enums\OrderState;
use App\Modules\Orders\Exceptions\InvalidTransition;
use App\Modules\Orders\Exceptions\OrderForbidden;
use App\Modules\Orders\Exceptions\RuleViolation;
use App\Modules\Orders\Models\ExtensionRequest;
use App\Modules\Orders\Models\Order;
use App\Shared\CommandReceipts;
use App\Shared\Dates;
use Carbon\Carbon;

/**
 * Report d'échéance : le freelance PROPOSE (motivé), le client ACCEPTE ou REFUSE. Seule l'acceptation change `due_at`, et la base
 * l'exige (déclencheur : un report accepté et enregistré, ancienne et nouvelle valeurs concordantes). L'ancienne échéance et la
 * décision sont conservées. Aucun report unilatéral, aucune remise à zéro automatique du délai.
 */
final class ExtensionRequests
{
    public const REASON_MIN = 15;

    private const WORK_STATES = [OrderState::InProgress, OrderState::RevisionRequested];

    /** @return array{0: Order, 1: bool} */
    public function request(User $freelancer, string $reference, string $proposedDate, string $reason, int $expectedVersion, string $operationKey): array
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < self::REASON_MIN || mb_strlen($reason) > 1000) {
            throw new RuleViolation('Expliquez la raison du report ('.self::REASON_MIN.' à 1000 caractères).');
        }
        try {
            $date = Carbon::createFromFormat('!Y-m-d', $proposedDate, 'Africa/Abidjan');
        } catch (\Throwable) {
            $date = null;
        }
        if ($date === false || $date === null || $date->format('Y-m-d') !== $proposedDate) {
            throw new RuleViolation('Indiquez une date valide.');
        }

        return $this->run($freelancer, $reference, 'orders.request_extension', $operationKey, ['v' => $expectedVersion, 'date' => $proposedDate, 'reason' => $reason],
            fn (Order $o) => $o->freelancer_id === $freelancer->getKey(),
            function (Order $locked) use ($freelancer, $date, $reason, $expectedVersion) {
                $this->assertOpen($locked, $expectedVersion);
                if ($locked->extensionRequests()->where('state', 'pending')->exists()) {
                    throw new RuleViolation('Une proposition de report est déjà en attente de réponse du client.');
                }
                $previous = $locked->due_at;
                // Même heure que l'échéance actuelle, nouvelle date : le report ne se négocie qu'en jours.
                $proposed = $previous->copy()->timezone('Africa/Abidjan')->setDate($date->year, $date->month, $date->day)->timezone($previous->getTimezone());
                if ($proposed->lte($previous) || $proposed->lte(now())) {
                    throw new RuleViolation('La nouvelle échéance doit être postérieure à l’échéance actuelle ('.Dates::format($previous).') et à aujourd’hui.');
                }
                $max = (int) config('freeci.orders.extension_max_days');
                if ($proposed->gt($previous->copy()->addDays($max))) {
                    throw new RuleViolation("Un report ne peut pas dépasser {$max} jours au-delà de l’échéance actuelle.");
                }
                $e = ExtensionRequest::create(['order_id' => $locked->getKey(), 'requested_by' => $freelancer->getKey(), 'previous_due_at' => $previous, 'proposed_due_at' => $proposed, 'reason' => $reason, 'state' => 'pending']);
                $locked->events()->create(['type' => 'extension_requested', 'actor_id' => $freelancer->getKey(), 'note' => $reason,
                    'meta' => ['extension_id' => $e->getKey(), 'previous_due_at' => $previous->toIso8601String(), 'proposed_due_at' => $proposed->toIso8601String()]]);
                $this->bump($locked);
            });
    }

    /** @return array{0: Order, 1: bool} */
    public function answer(User $client, string $reference, int $extensionId, bool $accept, ?string $note, int $expectedVersion, string $operationKey): array
    {
        $note = $note === null ? null : mb_substr(trim($note), 0, 1000);

        return $this->run($client, $reference, 'orders.answer_extension', $operationKey, ['v' => $expectedVersion, 'id' => $extensionId, 'accept' => $accept, 'note' => $note],
            fn (Order $o) => $o->client_id === $client->getKey(),
            function (Order $locked) use ($client, $extensionId, $accept, $note, $expectedVersion) {
                $this->assertOpen($locked, $expectedVersion);
                $e = ExtensionRequest::query()->whereKey($extensionId)->where('order_id', $locked->getKey())->lockForUpdate()->first();
                if ($e === null || $e->state !== 'pending' || ! $locked->due_at->eq($e->previous_due_at)) {
                    throw new InvalidTransition;
                }
                $now = now();
                $e->forceFill(['state' => $accept ? 'accepted' : 'declined', 'decided_by' => $client->getKey(), 'decided_at' => $now, 'decision_note' => $note])->save();
                $meta = ['extension_id' => $e->getKey(), 'previous_due_at' => $e->previous_due_at->toIso8601String(), 'proposed_due_at' => $e->proposed_due_at->toIso8601String()];
                if ($accept) {
                    $locked->forceFill(['due_at' => $e->proposed_due_at]);        // seul chemin de modification de l'échéance
                }
                $locked->events()->create(['type' => $accept ? 'extension_accepted' : 'extension_declined', 'actor_id' => $client->getKey(), 'note' => $note, 'meta' => $meta]);
                $this->bump($locked);
            });
    }

    /** @return array{0: Order, 1: bool} */
    public function withdraw(User $freelancer, string $reference, int $extensionId, int $expectedVersion, string $operationKey): array
    {
        return $this->run($freelancer, $reference, 'orders.withdraw_extension', $operationKey, ['v' => $expectedVersion, 'id' => $extensionId],
            fn (Order $o) => $o->freelancer_id === $freelancer->getKey(),
            function (Order $locked) use ($freelancer, $extensionId, $expectedVersion) {
                $this->assertOpen($locked, $expectedVersion);
                $e = ExtensionRequest::query()->whereKey($extensionId)->where('order_id', $locked->getKey())->lockForUpdate()->first();
                if ($e === null || $e->state !== 'pending') {
                    throw new InvalidTransition;
                }
                $e->forceFill(['state' => 'withdrawn', 'decided_by' => $freelancer->getKey(), 'decided_at' => now()])->save();
                $locked->events()->create(['type' => 'extension_withdrawn', 'actor_id' => $freelancer->getKey(), 'meta' => ['extension_id' => $e->getKey()]]);
                $this->bump($locked);
            });
    }

    private function assertOpen(Order $locked, int $expectedVersion): void
    {
        if ($locked->row_version !== $expectedVersion || ! in_array($locked->state, self::WORK_STATES, true) || $locked->due_at === null) {
            throw new InvalidTransition;
        }
    }

    private function bump(Order $locked): void
    {
        $locked->forceFill(['row_version' => $locked->row_version + 1, 'updated_at' => now()])->save();
    }

    /** @return array{0: Order, 1: bool} */
    private function run(User $actor, string $reference, string $action, string $key, array $payload, callable $isActor, callable $do): array
    {
        $order = Order::query()->where('reference', $reference)->first();
        if ($order === null || ! $isActor($order)) {
            throw new OrderForbidden;
        }
        [$id, $replayed] = CommandReceipts::once($actor->getKey(), $action, $key, ['reference' => $reference] + $payload, function () use ($order, $isActor, $do) {
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            if (! $isActor($locked)) {
                throw new OrderForbidden;
            }
            $do($locked);

            return $locked->getKey();
        });

        return [Order::findOrFail($id), $replayed];
    }
}
