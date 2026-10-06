<?php

namespace App\Modules\Messaging\Actions;

use App\Modules\Accounts\Actions\AccountStanding;
use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Enums\ServiceStatus;
use App\Modules\Catalog\Models\Service;
use App\Modules\Messaging\Exceptions\MessagingConflict;
use App\Modules\Messaging\Exceptions\MessagingForbidden;
use App\Modules\Messaging\Models\ContactBlock;
use App\Modules\Messaging\Models\Conversation;
use App\Modules\Missions\Models\Proposal;
use App\Modules\Orders\Models\Order;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/** Ouverture et rattachement des conversations, selon les droits de chaque contexte. Une conversation par contexte et par couple de participants. */
final class Conversations
{
    /** Conversation (existante ou nouvelle) d'un client avec le vendeur d'un service PUBLIÉ. @return array{0: Conversation, 1: bool} [conversation, créée] */
    public function forService(User $user, string $slug): array
    {
        $s = Service::query()->with('freelanceProfile')->where('slug', $slug)->first();
        if ($s === null || $s->status !== ServiceStatus::Published) {
            throw new MessagingForbidden;
        }
        $owner = $s->freelanceProfile->user_id;
        if ($owner === $user->getKey()) {
            throw new MessagingConflict('C’est votre propre service.');
        }
        $existing = Conversation::query()->where('kind', 'service')->where('service_id', $s->getKey())->where('client_id', $user->getKey())->first();
        if ($existing !== null) {
            return [$existing, false];
        }
        AccountStanding::assertCanStartNew($user);
        if (AccountStanding::suspended($owner)) {
            throw new MessagingConflict('Ce vendeur ne peut pas ouvrir de nouvelle conversation pour le moment.');
        }
        if (ConversationRules::blockedBetween($user->getKey(), $owner)) {
            throw new MessagingConflict('Vous ne pouvez pas ouvrir de nouvelle conversation avec ce vendeur.');
        }

        return [$this->create(['kind' => 'service', 'client_id' => $user->getKey(), 'freelancer_id' => $owner, 'service_id' => $s->getKey(), 'context_title' => mb_substr($s->title, 0, 160), 'created_by' => $user->getKey()]), true];
    }

    /** Conversation d'une proposition : le client de la mission et l'auteur, rien d'autre. Les autres candidats n'y ont aucun accès. @return array{0: Conversation, 1: bool} */
    public function forProposal(User $user, string $proposalId): array
    {
        $p = Proposal::query()->whereKey($proposalId)->with('mission.publishedVersion')->first();
        if ($p === null || ! ($p->freelancer_id === $user->getKey() || $p->mission->client_id === $user->getKey())) {
            throw new MessagingForbidden;
        }
        $existing = Conversation::query()->where('proposal_id', $p->getKey())->first();
        if ($existing !== null) {
            return [$existing, false];
        }
        AccountStanding::assertCanStartNew($user);
        if (ConversationRules::blockedBetween($p->mission->client_id, $p->freelancer_id)) {
            throw new MessagingConflict('Vous ne pouvez pas ouvrir de nouvelle conversation avec ce contact.');
        }
        $title = 'Mission : '.($p->mission->publishedVersion?->title ?? 'proposition');

        return [$this->create(['kind' => 'proposal', 'client_id' => $p->mission->client_id, 'freelancer_id' => $p->freelancer_id, 'proposal_id' => $p->getKey(), 'context_title' => mb_substr($title, 0, 160), 'created_by' => $user->getKey()]), true];
    }

    /** Conversation d'une commande (échanges indispensables : jamais bloquée tant que la commande est active). */
    public function forOrder(User $user, string $reference): Conversation
    {
        $o = Order::query()->where('reference', $reference)->with('agreement')->first();
        if ($o === null || ! $o->isParty($user)) {
            throw new MessagingForbidden;
        }

        return Conversation::query()->where('order_id', $o->getKey())->first()
            ?? $this->create(['kind' => 'order', 'client_id' => $o->client_id, 'freelancer_id' => $o->freelancer_id, 'order_id' => $o->getKey(), 'context_title' => mb_substr('Commande '.$o->reference.' · '.$o->agreement->service_title, 0, 160), 'created_by' => $user->getKey()]);
    }

    /** Rattache à la commande la conversation de la proposition retenue (le contexte est conservé ; aucune autre conversation n'est touchée). */
    public function linkProposalOrder(Order $order, string $proposalId): void
    {
        Conversation::query()->where('proposal_id', $proposalId)->whereNull('order_id')->where('client_id', $order->client_id)->where('freelancer_id', $order->freelancer_id)
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('conversations as c2')->where('c2.order_id', $order->getKey()))->update(['order_id' => $order->getKey(), 'updated_at' => now()]);
    }

    /** Rattache à la commande la conversation « service » du même couple, si elle existe et n'est pas déjà rattachée. */
    public function linkServiceOrder(Order $order): void
    {
        if ($order->service_id === null) {
            return;
        }
        Conversation::query()->where('kind', 'service')->where('service_id', $order->service_id)->whereNull('order_id')->where('client_id', $order->client_id)->where('freelancer_id', $order->freelancer_id)
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('conversations as c2')->where('c2.order_id', $order->getKey()))->update(['order_id' => $order->getKey(), 'updated_at' => now()]);
    }

    public function block(User $user, string $conversationId): void
    {
        $c = $this->owned($user, $conversationId);
        DB::table('contact_blocks')->insertOrIgnore(['blocker_id' => $user->getKey(), 'blocked_id' => $c->counterpartId($user), 'created_at' => now()]);
    }

    public function unblock(User $user, string $conversationId): void
    {
        $c = $this->owned($user, $conversationId);
        ContactBlock::query()->where('blocker_id', $user->getKey())->where('blocked_id', $c->counterpartId($user))->delete();
    }

    public function markRead(User $user, string $conversationId): void
    {
        $c = $this->owned($user, $conversationId);
        if ($c->last_message_id !== null) {
            Conversation::query()->whereKey($c->getKey())->update([$c->readColumn($user) => $c->last_message_id]);
        }
    }

    private function owned(User $user, string $id): Conversation
    {
        $c = Conversation::query()->whereKey($id)->first();
        if ($c === null || ! $c->isParticipant($user)) {
            throw new MessagingForbidden;
        }

        return $c;
    }

    private function create(array $attrs): Conversation
    {
        try {
            return Conversation::create($attrs);
        } catch (UniqueConstraintViolationException) {
            // course entre deux ouvertures : on retrouve LA conversation de ce contexte (et de ce client pour un service)
            $keys = $attrs['kind'] === 'service' ? ['kind', 'service_id', 'client_id'] : ($attrs['kind'] === 'proposal' ? ['proposal_id'] : ['order_id']);

            return Conversation::query()->where(array_intersect_key($attrs, array_flip($keys)))->firstOrFail();
        }
    }
}
