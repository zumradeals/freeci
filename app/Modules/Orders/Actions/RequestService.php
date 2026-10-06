<?php

namespace App\Modules\Orders\Actions;

use App\Integrations\Payments\PaymentMode;
use App\Modules\Accounts\Actions\AccountStanding;
use App\Modules\Accounts\Models\AccountRole;
use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Enums\ServiceStatus;
use App\Modules\Catalog\Exceptions\ServiceNotAvailable;
use App\Modules\Catalog\Exceptions\ServiceNotFound;
use App\Modules\Catalog\Models\Service;
use App\Modules\Messaging\Actions\Conversations;
use App\Modules\Orders\Enums\OrderState;
use App\Modules\Orders\Exceptions\OrderForbidden;
use App\Modules\Orders\Exceptions\OwnService;
use App\Modules\Orders\Exceptions\PendingRequestExists;
use App\Modules\Orders\Exceptions\RequestsClosed;
use App\Modules\Orders\Exceptions\ServiceChanged;
use App\Modules\Orders\Models\Order;
use App\Shared\CommandReceipts;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * `Orders\RequestService` (docs/02 §6) : le client demande une prestation. Crée la commande « en attente de réponse »,
 * FIGE l'accord (copie des conditions du service à cet instant), enregistre le brief textuel et l'historique.
 * Aucun paiement, aucun démarrage de travail, aucune échéance de réalisation.
 */
final class RequestService
{
    /**
     * @param  list<string>  $answers  une réponse par « élément à fournir » du service, dans l'ordre
     * @return array{0: Order, 1: bool} [commande, vrai si répétition de la même opération]
     *
     * @throws ServiceNotFound|ServiceNotAvailable|OwnService|RequestsClosed|ServiceChanged|PendingRequestExists|OrderForbidden|ValidationException
     */
    public function __invoke(User $client, string $serviceSlug, int $expectedServiceVersion, array $answers, ?string $notes, bool $conditionsAccepted, string $operationKey): array
    {
        if (! $client->hasRole(AccountRole::CLIENT)) {
            throw new OrderForbidden;
        }

        AccountStanding::assertCanStartNew($client);

        $service = Service::query()->with(['category', 'freelanceProfile'])->where('slug', $serviceSlug)->first();
        if ($service === null) {
            throw new ServiceNotFound;
        }
        if ($service->status->isWithdrawn()) {
            throw new ServiceNotAvailable;
        }
        if (! Service::query()->published()->whereKey($service->getKey())->exists()) {
            throw new ServiceNotFound;
        }
        if ($service->freelanceProfile->user_id === $client->getKey()) {
            throw new OwnService;
        }
        if (! $service->accepts_requests) {
            throw new RequestsClosed;
        }

        $brief = $this->validatedBrief($service, $answers, $notes, $conditionsAccepted);

        [$orderId, $replayed] = CommandReceipts::once(
            $client->getKey(), 'orders.request_service', $operationKey,
            ['service' => $service->getKey(), 'version' => $expectedServiceVersion, 'brief' => $brief, 'accepted' => $conditionsAccepted],
            fn () => $this->create($client, $service, $expectedServiceVersion, $brief),
        );

        return [Order::findOrFail($orderId), $replayed];
    }

    /** @return array{answers: list<array{label: string, answer: string}>, notes: ?string} */
    private function validatedBrief(Service $service, array $answers, ?string $notes, bool $conditionsAccepted): array
    {
        $errors = [];
        $items = [];
        foreach ($service->client_inputs as $i => $label) {
            $answer = trim((string) ($answers[$i] ?? ''));
            if ($answer === '') {
                $errors["answers.$i"] = 'Ce champ est obligatoire.';
            } elseif (mb_strlen($answer) > 1000) {
                $errors["answers.$i"] = 'Saisissez au plus 1 000 caractères.';
            }
            $items[] = ['label' => $label, 'answer' => $answer];
        }
        $notes = $notes === null ? null : trim($notes);
        if ($notes !== null && mb_strlen($notes) > 3000) {
            $errors['notes'] = 'Saisissez au plus 3 000 caractères.';
        }
        if (! $conditionsAccepted) {
            $errors['conditions'] = 'Vous devez accepter les conditions de la demande pour l’envoyer.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return ['answers' => $items, 'notes' => $notes === '' ? null : $notes];
    }

    private function create(User $client, Service $service, int $expectedVersion, array $brief): string
    {
        // Verrou du service : les conditions copiées sont celles de cette version précise, sans modification concurrente.
        $locked = Service::query()->whereKey($service->getKey())->lockForUpdate()->first();
        if ($locked->status !== ServiceStatus::Published || ! $locked->accepts_requests) {
            throw new ServiceNotAvailable;
        }
        if ($locked->row_version !== $expectedVersion) {
            throw new ServiceChanged;
        }
        $locked->load(['category', 'freelanceProfile']);

        $now = now();
        $responseHours = (int) config('freeci.orders.response_hours');
        $paymentHours = (int) config('freeci.orders.payment_hours');
        $freelancerId = $locked->freelanceProfile->user_id;
        $reference = 'FC-'.$now->format('ym').'-'.str_pad((string) DB::selectOne("select nextval('order_reference_seq') as n")->n, 5, '0', STR_PAD_LEFT);

        try {
            $order = Order::create([
                'reference' => $reference,
                'client_id' => $client->getKey(),
                'freelancer_id' => $freelancerId,
                'service_id' => $locked->getKey(),
                'state' => OrderState::AwaitingAcceptance,
                'requested_at' => $now,
                'response_deadline_at' => $now->copy()->addHours($responseHours),
                'is_demo' => $client->is_demo || $locked->is_demo || $locked->freelanceProfile->is_demo,
                'environment' => PaymentMode::orderEnvironment(),          // fixé À LA CRÉATION, immuable : « test » (sandbox) ou « live »
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new PendingRequestExists;
        }

        app(Conversations::class)->linkServiceOrder($order);       // le fil de discussion du service se poursuit dans la commande

        $order->agreement()->create([
            'service_id' => $locked->getKey(),
            'service_row_version' => $locked->row_version,
            'service_title' => $locked->title,
            'service_summary' => $locked->summary,
            'category_name' => $locked->category->name,
            'seller_name' => $locked->freelanceProfile->display_name,
            'scope' => $locked->scope,
            'price_xof' => $locked->price_xof,
            'delivery_days' => $locked->delivery_days,
            'revisions_included' => $locked->revisions_included,
            'deliverables' => $locked->deliverables,
            'exclusions' => $locked->exclusions,
            'client_inputs' => $locked->client_inputs,
            'delivery_requires_files' => $locked->delivery_requires_files,
            'delivery_mode' => $locked->delivery_requires_files ? 'files' : 'message',      // choix explicite de l'auteur du service, figé dans l'accord
            'brief_requires_files' => $locked->brief_requires_files,   // exigence figée : le service pourra changer, pas l'accord
            'response_hours' => $responseHours,
            'payment_hours' => $paymentHours,
            'commission_bp' => (int) config('freeci.finance.commission_bp'), 'commission_policy' => (string) config('freeci.finance.commission_policy'),    // conditions financières FIGÉES à l'accord
            'conditions_version' => config('freeci.orders.conditions_version'),
            'conditions_accepted_at' => $now,
        ]);
        $order->brief()->create(['answers' => $brief['answers'], 'notes' => $brief['notes']]);
        $order->events()->create([
            'type' => 'requested', 'actor_id' => $client->getKey(), 'to_state' => OrderState::AwaitingAcceptance->value,
            'meta' => ['service_row_version' => $locked->row_version, 'response_deadline_at' => $order->response_deadline_at->toIso8601String()],
        ]);

        return $order->getKey();
    }
}
