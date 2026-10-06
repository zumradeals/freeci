<?php

namespace App\Modules\Missions\Actions;

use App\Integrations\Payments\PaymentMode;
use App\Modules\Accounts\Actions\AccountStanding;
use App\Modules\Accounts\Models\User;
use App\Modules\Finance\PaymentGate;
use App\Modules\Messaging\Actions\Conversations;
use App\Modules\Missions\Exceptions\MissionConflict;
use App\Modules\Missions\Exceptions\MissionForbidden;
use App\Modules\Missions\Models\Mission;
use App\Modules\Missions\Models\Proposal;
use App\Modules\Missions\Models\ProposalVersion;
use App\Modules\Missions\Support\MissionHistory;
use App\Modules\Orders\Enums\OrderState;
use App\Modules\Orders\Models\Order;
use App\Shared\CommandReceipts;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * `Missions\SelectProposal` : le client retient UNE version précise d'une proposition. Verrous dans un ordre fixe (mission → proposition),
 * version courante et valide, mission ouverte et non réservée. Effets dans la même transaction : mission « réservée », proposition « retenue »,
 * et UNE commande « en attente de paiement » dont l'accord est FIGÉ par copie de la proposition. Rien ne démarre : le travail exige un paiement
 * confirmé côté serveur et un brief complet (le brief est saisi ici). La mission n'est « attribuée » qu'à la confirmation du paiement.
 * Exclusivité : verrou de ligne + index unique partiel `order_mission_live_uq` + clé d'opération.
 */
final class SelectProposal
{
    /**
     * @param  list<string>  $answers  une réponse par élément du brief de la mission, dans l'ordre
     * @return array{0: Order, 1: bool}
     */
    public function __invoke(User $client, string $missionId, string $proposalVersionId, int $expectedMissionVersion, array $answers, ?string $notes, bool $conditionsAccepted, string $operationKey): array
    {
        AccountStanding::assertCanStartNew($client);
        $mission = Mission::query()->whereKey($missionId)->where('client_id', $client->getKey())->with('publishedVersion')->first() ?? throw new MissionForbidden;
        $brief = $this->brief($mission, $answers, $notes, $conditionsAccepted);

        [$orderId, $replayed] = CommandReceipts::once($client->getKey(), 'missions.select_proposal', $operationKey,
            ['mission' => $missionId, 'pv' => $proposalVersionId, 'version' => $expectedMissionVersion, 'brief' => $brief, 'accepted' => $conditionsAccepted],
            fn () => $this->create($client, $missionId, $proposalVersionId, $expectedMissionVersion, $brief));

        return [Order::findOrFail($orderId), $replayed];
    }

    /** @return array{answers: list<array{label: string, answer: string}>, notes: ?string} */
    private function brief(Mission $m, array $answers, ?string $notes, bool $accepted): array
    {
        $errors = [];
        $items = [];
        foreach ($m->publishedVersion?->client_inputs ?? [] as $i => $label) {
            $a = trim((string) ($answers[$i] ?? ''));
            if ($a === '') {
                $errors["answers.$i"] = 'Ce champ est obligatoire.';
            } elseif (mb_strlen($a) > 1000) {
                $errors["answers.$i"] = 'Saisissez au plus 1 000 caractères.';
            }
            $items[] = ['label' => $label, 'answer' => $a];
        }
        $notes = $notes === null ? null : trim($notes);
        if ($notes !== null && mb_strlen($notes) > 3000) {
            $errors['notes'] = 'Saisissez au plus 3 000 caractères.';
        }
        if (! $accepted) {
            $errors['conditions'] = 'Vous devez confirmer que vous avez lu les conditions de la proposition pour la retenir.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return ['answers' => $items, 'notes' => $notes === '' ? null : $notes];
    }

    private function create(User $client, string $missionId, string $proposalVersionId, int $expectedVersion, array $brief): string
    {
        $m = Mission::query()->whereKey($missionId)->lockForUpdate()->firstOrFail();                 // 1) mission
        if ($m->client_id !== $client->getKey()) {
            throw new MissionForbidden;
        }
        $pv = ProposalVersion::query()->whereKey($proposalVersionId)->first();
        $p = $pv === null ? null : Proposal::query()->whereKey($pv->proposal_id)->where('mission_id', $m->getKey())->lockForUpdate()->first();   // 2) proposition
        if ($pv === null || $p === null) {
            throw new MissionForbidden;
        }
        if (AccountStanding::suspended($p->freelancer_id ?? '')) {
            throw new MissionConflict('Ce candidat ne peut pas recevoir de nouvelle commande pour le moment. Choisissez une autre proposition.');
        }
        if ($m->status !== 'open') {
            throw new MissionConflict($m->status === 'reserved' ? 'Une proposition est déjà retenue pour cette mission : un seul choix est possible.' : 'Cette mission n’accepte plus de sélection dans son état actuel.');
        }
        if ($m->row_version !== $expectedVersion) {
            throw new MissionConflict('La mission a changé pendant que vous consultiez les propositions. Rechargez la page avant de choisir ; rien n’a été retenu.');
        }
        $live = $m->publishedVersion;
        if ($p->state !== 'active' || (int) $p->versions()->max('number') !== $pv->number) {
            throw new MissionConflict('Cette version n’est plus la proposition courante de son auteur (révisée ou retirée). Rechargez la page.');
        }
        if ($pv->mission_version_id !== $live->getKey()) {
            throw new MissionConflict('Le besoin a été modifié depuis cette proposition : son auteur doit la reconfirmer avant que vous puissiez la retenir.');
        }
        if ($pv->valid_until->lte(now())) {
            throw new MissionConflict('Cette proposition n’est plus valide (durée de validité dépassée).');
        }
        if (MissionLifecycle::selectionEnd($live)->lte(now())) {
            throw new MissionConflict('La période de sélection est dépassée.');
        }
        $freelancer = User::query()->with('freelanceProfile')->findOrFail($p->freelancer_id);

        $now = now();
        $reference = 'FC-'.$now->format('ym').'-'.str_pad((string) DB::selectOne("select nextval('order_reference_seq') as n")->n, 5, '0', STR_PAD_LEFT);
        $paymentHours = (int) config('freeci.orders.payment_hours');
        try {
            $order = Order::create([
                'reference' => $reference, 'client_id' => $client->getKey(), 'freelancer_id' => $freelancer->getKey(), 'service_id' => null, 'origin' => 'mission',
                'mission_id' => $m->getKey(), 'proposal_version_id' => $pv->getKey(), 'state' => OrderState::AwaitingPayment,
                'requested_at' => $now, 'response_deadline_at' => $now, 'accepted_at' => $now,            // la proposition vaut acceptation du freelance : pas de délai de réponse
                'is_demo' => $client->is_demo || $m->is_demo || (bool) $freelancer->freelanceProfile?->is_demo,
                'environment' => PaymentMode::orderEnvironment(),          // fixé À LA CRÉATION, immuable
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new MissionConflict('Une commande existe déjà pour cette mission : un seul choix est possible.');
        }

        app(Conversations::class)->linkProposalOrder($order, $p->getKey());       // contexte conservé ; les fils des autres candidats ne sont pas touchés

        $order->agreement()->create([
            'origin' => 'mission', 'service_id' => null, 'service_row_version' => null, 'mission_id' => $m->getKey(), 'mission_version_id' => $live->getKey(), 'proposal_version_id' => $pv->getKey(),
            'service_title' => $live->title, 'service_summary' => mb_substr($live->description, 0, 300), 'category_name' => $live->category->name,
            'seller_name' => $freelancer->freelanceProfile?->display_name ?? $freelancer->name,
            'scope' => $pv->scope, 'price_xof' => $pv->price_xof, 'delivery_days' => $pv->delivery_days, 'revisions_included' => $pv->revisions_included,
            'deliverables' => $pv->deliverables, 'exclusions' => [], 'client_inputs' => $live->client_inputs,
            'delivery_requires_files' => $pv->delivery_mode === 'files', 'delivery_mode' => $pv->delivery_mode, 'brief_requires_files' => $live->brief_requires_files,
            'response_hours' => (int) config('freeci.orders.response_hours'), 'payment_hours' => $paymentHours,
            'conditions_version' => config('freeci.orders.conditions_version'), 'conditions_accepted_at' => $now,
        ]);
        $order->brief()->create(['answers' => $brief['answers'], 'notes' => $brief['notes']]);

        // Le paiement n'est « ouvert » (échéance de 24 h) que si les paiements sont ouverts pour CETTE commande ; sinon aucune échéance de paiement.
        $open = app(PaymentGate::class)->allows($order);
        $order->forceFill(['payment_deadline_at' => $open ? $now->copy()->addHours($paymentHours) : null])->save();
        $order->events()->create(['type' => 'proposal_selected', 'actor_id' => $client->getKey(), 'to_state' => OrderState::AwaitingPayment->value,
            'meta' => ['mission' => $m->slug, 'proposal_version' => $pv->number, 'payment_open' => $open]]);

        $p->forceFill(['state' => 'selected', 'row_version' => $p->row_version + 1])->save();
        $m->forceFill(['status' => 'reserved', 'selected_proposal_version_id' => $pv->getKey(), 'row_version' => $m->row_version + 1])->save();
        MissionHistory::log($m->getKey(), 'proposal_selected', $client, 'client', null, ['order' => $order->reference, 'proposal_version' => $pv->number], $live->getKey(), $p->getKey());

        return $order->getKey();
    }
}
