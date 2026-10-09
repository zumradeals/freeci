<?php

namespace App\Modules\Orders\Queries;

use App\Modules\Accounts\Models\User;
use App\Modules\Messaging\Actions\ConversationRules;
use App\Modules\Messaging\Models\Conversation;
use App\Shared\Dates;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lecture des offres personnalisées pour la personne connectée (jamais pour un tiers). L'état affiché tient compte de la validité : une offre en attente dont la date est passée s'affiche « expirée »
 * même avant le passage de la tâche planifiée.
 */
final class OfferQueries
{
    private const STATES = ['pending' => ['En attente de réponse', 'wait'], 'accepted' => ['Acceptée', 'ok'], 'declined' => ['Refusée', 'no'], 'withdrawn' => ['Retirée', 'off'], 'expired' => ['Expirée', 'off']];

    /**
     * Contexte du formulaire d'offre : null si la personne n'est pas le freelance de cette conversation.
     *
     * @return array{id: string, with: string, context: string, can: bool}|null
     */
    public function composeContext(User $viewer, string $conversationId): ?array
    {
        $c = Conversation::query()->whereKey($conversationId)->with('client')->first();
        if ($c === null || $c->freelancer_id !== $viewer->getKey()) {
            return null;
        }

        return ['id' => (string) $c->getKey(), 'with' => $c->client->name, 'context' => $c->context_title, 'can' => $this->canPropose($viewer, $c)];
    }

    /** @return array<string, mixed>|null */
    public function find(User $viewer, string $offerId): ?array
    {
        $o = DB::table('custom_offers')->where('id', $offerId)->where(fn ($q) => $q->where('client_id', $viewer->getKey())->orWhere('freelancer_id', $viewer->getKey()))->first();

        return $o === null ? null : $this->present($o, $viewer);
    }

    /**
     * Offres d'une conversation à afficher dans son fil (les plus anciennes d'abord).
     *
     * @return list<array<string, mixed>>
     */
    public function forThread(User $viewer, string $conversationId, ?Carbon $since = null): array
    {
        return DB::table('custom_offers')->where('conversation_id', $conversationId)->when($since, fn ($q) => $q->where('created_at', '>=', $since))->orderBy('created_at')->get()
            ->map(fn ($o) => $this->present($o, $viewer))->all();
    }

    /** Le freelance peut-il proposer une offre dans cette conversation maintenant ? (kind « service », pas de commande, pas d'offre en attente, échanges possibles) */
    public function canPropose(User $viewer, Conversation $c): bool
    {
        return $c->freelancer_id === $viewer->getKey() && $c->kind === 'service' && $c->order_id === null && ConversationRules::canSend($c)
            && ! DB::table('custom_offers')->where('conversation_id', $c->getKey())->where('state', 'pending')->where('valid_until', '>', now())->exists();
    }

    /** @return array<string, mixed> */
    private function present(object $o, User $viewer): array
    {
        $state = $o->state === 'pending' && Carbon::parse($o->valid_until)->lte(now()) ? 'expired' : $o->state;
        $mine = $o->freelancer_id === $viewer->getKey();
        $otherId = $mine ? $o->client_id : $o->freelancer_id;
        $freelancerName = (string) (DB::table('freelance_profiles')->where('user_id', $o->freelancer_id)->value('display_name') ?: DB::table('users')->where('id', $o->freelancer_id)->value('name'));
        [$label, $tone] = self::STATES[$state];

        return [
            'id' => $o->id, 'conversation' => $o->conversation_id, 'mine' => $mine, 'iAmClient' => ! $mine, 'state' => $state, 'stateLabel' => $label, 'tone' => $tone, 'title' => $o->title, 'scope' => $o->scope,
            'deliverables' => json_decode($o->deliverables, true), 'clientInputs' => json_decode($o->client_inputs, true), 'price' => (int) $o->price_xof, 'days' => (int) $o->delivery_days, 'revisions' => (int) $o->revisions_included,
            'deliveryMode' => $o->delivery_mode, 'validUntil' => Dates::format(Carbon::parse($o->valid_until)), 'validUntilShort' => Carbon::parse($o->valid_until)->translatedFormat('j F'),
            'at' => Carbon::parse($o->created_at)->toIso8601String(), 'when' => Dates::format(Carbon::parse($o->created_at)), 'respondedAt' => $o->responded_at === null ? null : Dates::format(Carbon::parse($o->responded_at)),
            'declineNote' => $o->decline_note, 'orderReference' => $o->order_id === null ? null : (string) DB::table('orders')->where('id', $o->order_id)->value('reference'),
            'freelancerName' => $freelancerName, 'withName' => (string) DB::table('users')->where('id', $otherId)->value('name'), 'withId' => (string) $otherId,
            'actionable' => $state === 'pending', 'replaced' => $state === 'withdrawn' && DB::table('custom_offers')->where('conversation_id', $o->conversation_id)->where('created_at', '>', $o->created_at)->exists(),
        ];
    }
}
