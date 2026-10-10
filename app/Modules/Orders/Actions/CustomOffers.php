<?php

namespace App\Modules\Orders\Actions;

use App\Integrations\Payments\PaymentMode;
use App\Modules\Accounts\Actions\AccountStanding;
use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Support\PrivateContact;
use App\Modules\Finance\Commission\CommissionTerms;
use App\Modules\Finance\PaymentGate;
use App\Modules\Messaging\Actions\ConversationRules;
use App\Modules\Messaging\Models\Conversation;
use App\Modules\Notifications\Actions\Notify;
use App\Modules\Orders\Enums\OrderState;
use App\Modules\Orders\Exceptions\OfferConflict;
use App\Modules\Orders\Models\Order;
use App\Shared\CommandReceipts;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Offre personnalisée (F-09). Le freelance envoie, depuis une conversation née d'une question sur son service (pas encore rattachée à une commande), une offre dont le CONTENU est inmodifiable
 * (déclencheur en base) : pour changer, il la retire et en envoie une autre. Une seule offre en attente par conversation. Le client l'accepte (conditions + réponses aux éléments à fournir) :
 * cela crée une commande NORMALE « en attente de paiement » d'origine « offre » — l'offre vaut acceptation du freelance, comme la proposition retenue d'une mission — avec un accord figé.
 * Elle expire d'elle-même à sa date de validité, sans conséquence. Rien n'est négocié ici : la discussion reste dans la conversation.
 */
final class CustomOffers
{
    public const PRICE = [5000, 5000000];

    public const DAYS = [1, 180];

    public const REVISIONS = [0, 10];

    public const SCOPE = [50, 3000];

    public const VALID_DAYS = [1, 30];

    public const MAX_ITEMS = 10;

    public function __construct(private Notify $notify) {}

    // ------------------------------------------------------------------ envoi

    /**
     * @param  array<string, mixed>  $in  title, scope, deliverables (texte, une ligne par livrable), client_inputs (idem), price_xof, delivery_days, revisions_included, delivery_mode, valid_days
     * @return array{0: string, 1: bool} [identifiant de l'offre, répétition de la même opération]
     */
    public function send(User $freelancer, string $conversationId, array $in, string $operationKey): array
    {
        $d = $this->validated($in);
        AccountStanding::assertCanStartNew($freelancer);
        $c = Conversation::query()->whereKey($conversationId)->first();
        if ($c === null || $c->freelancer_id !== $freelancer->getKey()) {
            throw new OfferConflict('Conversation introuvable.');
        }

        return CommandReceipts::once($freelancer->getKey(), 'offers.send', $operationKey, ['c' => $conversationId, 'd' => $d], function () use ($freelancer, $conversationId, $d) {
            $conv = Conversation::query()->whereKey($conversationId)->lockForUpdate()->firstOrFail();
            if ($conv->kind !== 'service' || $conv->order_id !== null) {
                throw new OfferConflict('Une offre personnalisée se propose depuis une conversation née d’une question sur votre service, avant toute commande.');
            }
            if (! ConversationRules::canSend($conv) || AccountStanding::suspended($conv->client_id)) {
                throw new OfferConflict('Cette personne ne peut pas recevoir d’offre pour le moment.');
            }
            $this->lapse($conv->getKey());
            $id = (string) Str::uuid();
            try {
                DB::table('custom_offers')->insert([
                    'id' => $id, 'conversation_id' => $conv->getKey(), 'client_id' => $conv->client_id, 'freelancer_id' => $freelancer->getKey(), 'title' => $d['title'], 'scope' => $d['scope'],
                    'deliverables' => json_encode($d['deliverables'], JSON_UNESCAPED_UNICODE), 'client_inputs' => json_encode($d['client_inputs'], JSON_UNESCAPED_UNICODE), 'price_xof' => $d['price_xof'],
                    'delivery_days' => $d['delivery_days'], 'revisions_included' => $d['revisions_included'], 'delivery_mode' => $d['delivery_mode'], 'valid_until' => now()->addDays($d['valid_days']),
                    'state' => 'pending', 'created_at' => now(), 'updated_at' => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                throw new OfferConflict('Une offre est déjà en attente dans cette conversation : retirez-la avant d’en envoyer une autre.');
            }
            $name = (string) DB::table('freelance_profiles')->where('user_id', $freelancer->getKey())->value('display_name') ?: $freelancer->name;
            ($this->notify)($conv->client_id, 'offer_received', 'offer_received:'.$id, $name.' vous envoie une offre personnalisée', $d['title'], 'offers.show', ['offer' => $id]);

            return $id;
        });
    }

    // ------------------------------------------------------------------ retrait, refus

    public function withdraw(User $freelancer, string $offerId): void
    {
        $this->settle($offerId, $freelancer, 'freelancer', 'withdrawn', null, function (object $o) {
            ($this->notify)($o->client_id, 'offer_update', 'offer_withdrawn:'.$o->id, 'Une offre a été retirée', $o->title, 'messages.show', ['conversation' => $o->conversation_id]);
        });
    }

    public function decline(User $client, string $offerId, ?string $note): void
    {
        $note = $note === null ? null : trim($note);
        if ($note !== null && mb_strlen($note) > 500) {
            throw new OfferConflict('Le motif fait au plus 500 caractères.');
        }
        if ($note !== null && $note !== '' && PrivateContact::found($note)) {
            throw new OfferConflict('Le motif ne doit contenir ni adresse e-mail, ni numéro de téléphone, ni lien de messagerie.');
        }
        $this->settle($offerId, $client, 'client', 'declined', $note === '' ? null : $note, function (object $o) {
            ($this->notify)($o->freelancer_id, 'offer_update', 'offer_declined:'.$o->id, 'Votre offre a été refusée', $o->title, 'messages.show', ['conversation' => $o->conversation_id]);
        });
    }

    // ------------------------------------------------------------------ acceptation

    /**
     * @param  list<string>  $answers  une réponse par élément à fournir, dans l'ordre
     * @return array{0: Order, 1: bool} [commande, répétition]
     */
    public function accept(User $client, string $offerId, array $answers, ?string $notes, bool $conditionsAccepted, string $operationKey): array
    {
        AccountStanding::assertCanStartNew($client);
        $offer = DB::table('custom_offers')->where('id', $offerId)->where('client_id', $client->getKey())->first();
        if ($offer === null) {
            throw new OfferConflict('Offre introuvable.');
        }
        $brief = $this->brief(json_decode($offer->client_inputs, true) ?: [], $answers, $notes, $conditionsAccepted);

        [$orderId, $replayed] = CommandReceipts::once($client->getKey(), 'offers.accept', $operationKey, ['o' => $offerId, 'b' => $brief], fn () => $this->create($client, $offerId, $brief));

        return [Order::findOrFail($orderId), $replayed];
    }

    /** @param  array{answers: list<array{label: string, answer: string}>, notes: ?string}  $brief */
    private function create(User $client, string $offerId, array $brief): string
    {
        $o = DB::table('custom_offers')->where('id', $offerId)->lockForUpdate()->first();
        if ($o === null || $o->client_id !== $client->getKey()) {
            throw new OfferConflict('Offre introuvable.');
        }
        $this->lapse($o->conversation_id);
        $o = DB::table('custom_offers')->where('id', $offerId)->first();
        if ($o->state !== 'pending') {
            throw new OfferConflict(match ($o->state) {
                'expired' => 'Cette offre a expiré : écrivez au freelance pour en recevoir une nouvelle.',
                'withdrawn' => 'Cette offre a été retirée par le freelance.',
                'accepted' => 'Cette offre est déjà acceptée.',
                default => 'Cette offre n’est plus disponible.',
            });
        }
        if (AccountStanding::suspended($o->freelancer_id)) {
            throw new OfferConflict('Ce freelance ne peut pas recevoir de nouvelle commande pour le moment.');
        }
        $freelancer = User::query()->with('freelanceProfile')->findOrFail($o->freelancer_id);
        $now = now();
        $paymentHours = (int) config('freeci.orders.payment_hours');
        $reference = 'FC-'.$now->format('ym').'-'.str_pad((string) DB::selectOne("select nextval('order_reference_seq') as n")->n, 5, '0', STR_PAD_LEFT);
        try {
            $order = Order::create([
                'reference' => $reference, 'client_id' => $client->getKey(), 'freelancer_id' => $freelancer->getKey(), 'service_id' => null, 'origin' => 'offer', 'offer_id' => $o->id,
                'state' => OrderState::AwaitingPayment, 'requested_at' => $now, 'response_deadline_at' => $now, 'accepted_at' => $now,            // l'offre vaut acceptation du freelance : pas de délai de réponse
                'is_demo' => $client->is_demo || (bool) $freelancer->freelanceProfile?->is_demo,
                'environment' => PaymentMode::orderEnvironment(),            // fixé À LA CRÉATION, immuable
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new OfferConflict('Cette offre est déjà acceptée.');
        }
        $terms = app(CommissionTerms::class)->forOrder($order->getKey(), (string) $order->freelancer_id);      // taux figé : normal, ou offert
        $order->agreement()->create($terms + [
            'origin' => 'offer', 'offer_id' => $o->id, 'service_id' => null, 'service_row_version' => null,
            'service_title' => $o->title, 'service_summary' => mb_substr($o->scope, 0, 300), 'category_name' => 'Offre personnalisée',
            'seller_name' => $freelancer->freelanceProfile?->display_name ?? $freelancer->name,
            'scope' => $o->scope, 'price_xof' => $o->price_xof, 'delivery_days' => $o->delivery_days, 'revisions_included' => $o->revisions_included,
            'deliverables' => json_decode($o->deliverables, true), 'exclusions' => [], 'client_inputs' => json_decode($o->client_inputs, true),
            'delivery_requires_files' => $o->delivery_mode === 'files', 'delivery_mode' => $o->delivery_mode, 'brief_requires_files' => false,
            'response_hours' => (int) config('freeci.orders.response_hours'), 'payment_hours' => $paymentHours,
            'conditions_version' => config('freeci.orders.conditions_version'), 'conditions_accepted_at' => $now,
        ]);
        $order->brief()->create(['answers' => $brief['answers'], 'notes' => $brief['notes']]);
        $open = app(PaymentGate::class)->allows($order);
        $order->forceFill(['payment_deadline_at' => $open ? $now->copy()->addHours($paymentHours) : null])->save();
        $order->events()->create(['type' => 'offer_accepted', 'actor_id' => $client->getKey(), 'to_state' => OrderState::AwaitingPayment->value, 'meta' => ['offer' => $o->id, 'payment_open' => $open]]);

        DB::table('custom_offers')->where('id', $o->id)->update(['state' => 'accepted', 'order_id' => $order->getKey(), 'responded_at' => $now, 'row_version' => $o->row_version + 1, 'updated_at' => $now]);
        // La conversation de l'offre devient celle de la commande (contexte conservé).
        DB::table('conversations')->where('id', $o->conversation_id)->whereNull('order_id')->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('conversations as c2')->where('c2.order_id', $order->getKey()))
            ->update(['order_id' => $order->getKey(), 'updated_at' => now()]);
        ($this->notify)($o->freelancer_id, 'offer_update', 'offer_accepted:'.$o->id, 'Votre offre est acceptée', $o->title.' : la commande attend le paiement du client.', 'orders.show', ['reference' => $order->reference]);

        return $order->getKey();
    }

    // ------------------------------------------------------------------ expiration

    /** Constate les offres échues (la validité est de toute façon évaluée à chaque lecture et à chaque action). @return int nombre d'offres expirées */
    public function expireDue(): int
    {
        $ids = DB::table('custom_offers')->where('state', 'pending')->where('valid_until', '<=', now())->pluck('conversation_id');
        $n = 0;
        foreach ($ids as $conversationId) {
            $n += $this->lapse((string) $conversationId);
        }

        return $n;
    }

    /** Passe à « expirée » l'offre en attente échue d'une conversation et prévient les deux personnes. @return int 0 ou 1 */
    private function lapse(string $conversationId): int
    {
        $row = DB::table('custom_offers')->where('conversation_id', $conversationId)->where('state', 'pending')->where('valid_until', '<=', now())->first();
        if ($row === null) {
            return 0;
        }
        $done = DB::table('custom_offers')->where('id', $row->id)->where('state', 'pending')->update(['state' => 'expired', 'responded_at' => now(), 'row_version' => $row->row_version + 1, 'updated_at' => now()]);
        if ($done === 1) {
            foreach ([$row->client_id, $row->freelancer_id] as $u) {
                ($this->notify)($u, 'offer_update', 'offer_expired:'.$row->id.':'.$u, 'Une offre personnalisée a expiré', $row->title, 'messages.show', ['conversation' => $row->conversation_id]);
            }
        }

        return $done;
    }

    // ------------------------------------------------------------------ interne

    private function settle(string $offerId, User $actor, string $side, string $to, ?string $note, \Closure $notify): void
    {
        DB::transaction(function () use ($offerId, $actor, $side, $to, $note, $notify) {
            $o = DB::table('custom_offers')->where('id', $offerId)->lockForUpdate()->first();
            if ($o === null || ($side === 'client' ? $o->client_id : $o->freelancer_id) !== $actor->getKey()) {
                throw new OfferConflict('Offre introuvable.');
            }
            $this->lapse($o->conversation_id);
            $o = DB::table('custom_offers')->where('id', $offerId)->first();
            if ($o->state !== 'pending') {
                throw new OfferConflict($o->state === $to ? 'Cette offre est déjà '.($to === 'withdrawn' ? 'retirée' : 'refusée').'.' : 'Cette offre n’est plus en attente.');
            }
            DB::table('custom_offers')->where('id', $o->id)->where('state', 'pending')->update(['state' => $to, 'decline_note' => $note, 'responded_at' => now(), 'row_version' => $o->row_version + 1, 'updated_at' => now()]);
            $notify($o);
        });
    }

    /** @return array{title: string, scope: string, deliverables: list<string>, client_inputs: list<string>, price_xof: int, delivery_days: int, revisions_included: int, delivery_mode: string, valid_days: int} */
    private function validated(array $in): array
    {
        $e = [];
        $title = trim((string) ($in['title'] ?? ''));
        $scope = trim((string) ($in['scope'] ?? ''));
        $lines = fn (string $k, int $max) => array_values(array_filter(array_map(fn ($l) => trim($l), preg_split('/\R/u', (string) ($in[$k] ?? '')) ?: []), fn ($l) => $l !== ''));
        $deliverables = $lines('deliverables', 200);
        $inputs = $lines('client_inputs', 120);
        $int = fn (string $k) => isset($in[$k]) && is_numeric($in[$k]) && (string) (int) $in[$k] === (string) $in[$k] ? (int) $in[$k] : null;
        $price = $int('price_xof');
        $days = $int('delivery_days');
        $rev = $int('revisions_included');
        $valid = $int('valid_days');
        $mode = ($in['delivery_mode'] ?? 'files') === 'message' ? 'message' : 'files';

        if (mb_strlen($title) < 3 || mb_strlen($title) > 160) {
            $e['title'] = 'Le titre fait de 3 à 160 caractères.';
        }
        if (mb_strlen($scope) < self::SCOPE[0] || mb_strlen($scope) > self::SCOPE[1]) {
            $e['scope'] = 'Décrivez ce qui est inclus ('.self::SCOPE[0].' à '.number_format(self::SCOPE[1], 0, ',', ' ').' caractères).';
        }
        if ($deliverables === [] || count($deliverables) > self::MAX_ITEMS || max(array_map('mb_strlen', $deliverables ?: ['x'])) > 200) {
            $e['deliverables'] = 'Indiquez de 1 à '.self::MAX_ITEMS.' livrables (un par ligne, 200 caractères au plus).';
        }
        if (count($inputs) > self::MAX_ITEMS || ($inputs !== [] && max(array_map('mb_strlen', $inputs)) > 120)) {
            $e['client_inputs'] = 'Au plus '.self::MAX_ITEMS.' éléments à fournir (un par ligne, 120 caractères au plus).';
        }
        if ($price === null || $price < self::PRICE[0] || $price > self::PRICE[1]) {
            $e['price_xof'] = 'Le prix va de '.number_format(self::PRICE[0], 0, ',', ' ').' à '.number_format(self::PRICE[1], 0, ',', ' ').' FCFA.';
        }
        if ($days === null || $days < self::DAYS[0] || $days > self::DAYS[1]) {
            $e['delivery_days'] = 'Le délai va de '.self::DAYS[0].' à '.self::DAYS[1].' jours.';
        }
        if ($rev === null || $rev < self::REVISIONS[0] || $rev > self::REVISIONS[1]) {
            $e['revisions_included'] = 'Les corrections incluses vont de '.self::REVISIONS[0].' à '.self::REVISIONS[1].'.';
        }
        if ($valid === null || $valid < self::VALID_DAYS[0] || $valid > self::VALID_DAYS[1]) {
            $e['valid_days'] = 'La validité de l’offre va de '.self::VALID_DAYS[0].' à '.self::VALID_DAYS[1].' jours.';
        }
        foreach (['title' => $title, 'scope' => $scope, 'deliverables' => implode("\n", $deliverables), 'client_inputs' => implode("\n", $inputs)] as $field => $text) {
            if (! isset($e[$field]) && PrivateContact::found($text)) {
                $e[$field] = 'Ne mettez ni adresse e-mail, ni numéro de téléphone, ni lien de messagerie dans une offre : les échanges se font dans FreeCI.';
            }
        }
        if ($e !== []) {
            throw ValidationException::withMessages($e);
        }

        return ['title' => $title, 'scope' => $scope, 'deliverables' => $deliverables, 'client_inputs' => $inputs, 'price_xof' => $price, 'delivery_days' => $days, 'revisions_included' => $rev, 'delivery_mode' => $mode, 'valid_days' => $valid];
    }

    /** @return array{answers: list<array{label: string, answer: string}>, notes: ?string} */
    private function brief(array $labels, array $answers, ?string $notes, bool $accepted): array
    {
        $errors = [];
        $items = [];
        foreach ($labels as $i => $label) {
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
            $errors['conditions'] = 'Vous devez accepter les conditions de la demande pour accepter l’offre.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return ['answers' => $items, 'notes' => $notes === '' ? null : $notes];
    }
}
