<?php

namespace App\Modules\Orders\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Files\Enums\FileState;
use App\Modules\Orders\Enums\OrderState;
use App\Modules\Orders\Exceptions\InvalidTransition;
use App\Modules\Orders\Exceptions\RuleViolation;
use App\Modules\Orders\Models\CorrectionRequest;
use App\Modules\Orders\Models\Delivery;
use App\Modules\Orders\Models\Order;

/**
 * `Orders\DeliverOrder` : le freelance SOUMET explicitement son brouillon. La version est attribuée ici, la livraison devient
 * immuable (déclencheur), les versions précédentes ne sont jamais touchées. Impossible tant qu'un fichier est en contrôle
 * ou qu'un fichier obligatoire manque. Une proposition de report en attente est retirée (le délai redevient celui en vigueur).
 */
final class SubmitDelivery extends ChangesOrder
{
    /** @return array{0: Order, 1: bool} */
    public function __invoke(User $freelancer, string $reference, string $deliveryId, int $expectedVersion, string $operationKey): array
    {
        return $this->transition(
            $freelancer, $reference, 'orders.submit_delivery', $operationKey, $expectedVersion,
            fn (Order $o) => $o->freelancer_id === $freelancer->getKey(),
            [OrderState::InProgress, OrderState::RevisionRequested], OrderState::Delivered, 'delivery_submitted',
            guard: function (Order $locked, User $actor) use ($deliveryId): array {
                $draft = Delivery::query()->whereKey($deliveryId)->where('order_id', $locked->getKey())->where('state', 'draft')->lockForUpdate()->first();
                if ($draft === null) {
                    throw new InvalidTransition;
                }
                $reasons = DeliveryDraft::blockers($locked, $draft);
                if ($reasons !== []) {
                    throw new RuleViolation('La livraison ne peut pas encore être soumise.', $reasons);
                }

                $version = (int) Delivery::query()->where('order_id', $locked->getKey())->where('state', 'submitted')->max('version') + 1;
                // Une livraison déposée après une demande de correction y répond : lien explicite demande → version.
                $answers = null;
                if ($locked->state === OrderState::RevisionRequested) {
                    $answers = CorrectionRequest::query()->where('order_id', $locked->getKey())->orderByDesc('number')->first();
                }
                $now = now();
                $draft->forceFill([
                    'state' => 'submitted', 'version' => $version, 'submitted_at' => $now, 'author_id' => $actor->getKey(),
                    'review_deadline_at' => $now->copy()->addDays((int) config('freeci.orders.review_days')),
                    'correction_request_id' => $answers?->getKey(),
                ])->save();

                $locked->extensionRequests()->where('state', 'pending')->get()->each(function ($e) use ($actor, $now, $locked) {
                    $e->forceFill(['state' => 'withdrawn', 'decided_by' => $actor->getKey(), 'decided_at' => $now, 'decision_note' => 'Retirée : livraison soumise'])->save();
                    $locked->events()->create(['type' => 'extension_withdrawn', 'actor_id' => $actor->getKey(), 'note' => 'Livraison soumise : la proposition de report est retirée.']);
                });

                return [
                    'delivery_id' => $draft->getKey(), 'version' => $version, 'answers_correction' => $answers?->number,
                    'files' => $draft->files()->where('state', FileState::Clean->value)->count(), 'review_deadline_at' => $draft->review_deadline_at->toIso8601String(),
                ];
            },
        );
    }
}
