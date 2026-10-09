<?php

namespace App\Modules\Messaging\Queries;

use App\Modules\Accounts\Models\User;
use App\Modules\Files\Enums\FileState;
use App\Modules\Files\Models\FileAsset;
use App\Modules\Messaging\Actions\ConversationRules;
use App\Modules\Messaging\Exceptions\MessagingForbidden;
use App\Modules\Messaging\Models\ContactBlock;
use App\Modules\Messaging\Models\Conversation;
use App\Modules\Messaging\Models\Message;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Queries\OfferQueries;
use App\Shared\Dates;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/** Lecture des conversations, TOUJOURS bornée aux deux participants (jamais d'accès implicite, administrateur compris). */
final class Inbox
{
    /** Messages non lus (hors les siens) d'une conversation pour $user. */
    private function unreadCount(Conversation $c, User $u): int
    {
        $read = (int) $c->{$c->readColumn($u)};

        return Message::query()->where('conversation_id', $c->getKey())->where('id', '>', $read)->where('sender_id', '!=', $u->getKey())->count();
    }

    public function totalUnread(User $u): int
    {
        return (int) DB::table('messages as m')->join('conversations as c', 'c.id', '=', 'm.conversation_id')->where('m.sender_id', '!=', $u->getKey())
            ->where(fn ($q) => $q->where(fn ($w) => $w->where('c.client_id', $u->getKey())->whereRaw('m.id > coalesce(c.client_read_id, 0)'))->orWhere(fn ($w) => $w->where('c.freelancer_id', $u->getKey())->whereRaw('m.id > coalesce(c.freelancer_read_id, 0)')))->count();
    }

    public function unreadForOrder(User $u, string $orderId): int
    {
        $c = Conversation::query()->where('order_id', $orderId)->first();

        return $c !== null && $c->isParticipant($u) ? $this->unreadCount($c, $u) : 0;
    }

    /** @return list<array<string, mixed>> */
    public function list(User $u): array
    {
        $kinds = ['service' => 'Service', 'proposal' => 'Proposition', 'order' => 'Commande'];

        return Conversation::query()->where(fn ($q) => $q->where('client_id', $u->getKey())->orWhere('freelancer_id', $u->getKey()))->whereNotNull('last_message_id')->with(['client', 'freelancer'])
            ->orderByDesc('last_message_at')->get()->map(function (Conversation $c) use ($u, $kinds) {
                $other = $c->client_id === $u->getKey() ? $c->freelancer : $c->client;
                $last = Message::query()->find($c->last_message_id);

                return [
                    'id' => $c->getKey(), 'with' => $other->name, 'withId' => (string) $other->getKey(), 'context' => $c->context_title, 'kind' => $kinds[$c->kind], 'unread' => $this->unreadCount($c, $u),
                    'when' => $c->last_message_at ? Dates::short($c->last_message_at) : '', 'snippet' => $last ? ($last->sender_id === $u->getKey() ? 'Vous : ' : '').mb_substr($last->body !== '' ? $last->body : '(pièce jointe)', 0, 90) : '',
                    'linkedOrder' => $c->order_id !== null,
                ];
            })->all();
    }

    /** @return list<array{name: string, since: string}> */
    public function blockedContacts(User $u): array
    {
        return ContactBlock::query()->where('blocker_id', $u->getKey())->get()->map(fn ($b) => ['name' => (string) DB::table('users')->where('id', $b->blocked_id)->value('name'), 'since' => Dates::format(Carbon::parse($b->created_at))])->all();
    }

    /**
     * Fil d'une conversation, paginé (les plus récents d'abord ; « avant » charge les plus anciens).
     *
     * @return array<string, mixed>
     */
    public function thread(User $u, string $conversationId, ?int $before = null): array
    {
        $c = Conversation::query()->whereKey($conversationId)->with(['client', 'freelancer'])->first();
        if ($c === null || ! $c->isParticipant($u)) {
            throw new MessagingForbidden;
        }
        $size = (int) config('freeci.messaging.page_size');
        $rows = Message::query()->where('conversation_id', $c->getKey())->when($before, fn ($q) => $q->where('id', '<', $before))->orderByDesc('id')->limit($size + 1)->get();
        $older = $rows->count() > $size;
        $rows = $rows->take($size)->reverse()->values();
        $files = FileAsset::query()->whereIn('message_id', $rows->pluck('id'))->get()->keyBy('message_id');
        $unreadFrom = (int) $c->{$c->readColumn($u)};
        $other = $c->client_id === $u->getKey() ? $c->freelancer : $c->client;
        $order = $c->order_id ? Order::query()->with('agreement')->find($c->order_id) : null;
        $iBlocked = ContactBlock::query()->where('blocker_id', $u->getKey())->where('blocked_id', $other->getKey())->exists();

        return [
            'id' => $c->getKey(), 'with' => $other->name, 'withId' => (string) $other->getKey(), 'context' => $c->context_title, 'kind' => $c->kind, 'orderReference' => $order?->reference, 'orderActive' => ConversationRules::essential($c),
            'lastId' => (int) $c->last_message_id, 'olderBefore' => $older ? (int) $rows->first()->id : null, 'unreadFrom' => $unreadFrom,
            'offers' => app(OfferQueries::class)->forThread($u, $c->getKey(), $older ? $rows->first()->created_at : null), 'canOffer' => app(OfferQueries::class)->canPropose($u, $c),
            'canSend' => ConversationRules::canSend($c), 'iBlocked' => $iBlocked, 'blockedByOther' => ! $iBlocked && ConversationRules::blockedBetween($c->client_id, $c->freelancer_id),
            'messages' => $rows->map(function (Message $m) use ($u, $files, $unreadFrom) {
                $f = $files->get($m->id);

                return [
                    'id' => $m->id, 'mine' => $m->sender_id === $u->getKey(), 'who' => $m->sender_id === $u->getKey() ? 'Vous' : (string) DB::table('users')->where('id', $m->sender_id)->value('name'),
                    'when' => Dates::format($m->created_at), 'at' => $m->created_at->toIso8601String(), 'body' => $m->body, 'unread' => $m->sender_id !== $u->getKey() && $m->id > $unreadFrom,
                    'file' => $f ? [
                        'name' => $f->original_name, 'size' => $f->size_bytes >= 1048576 ? number_format($f->size_bytes / 1048576, 1, ',', ' ').' Mo' : number_format($f->size_bytes / 1024, 0, ',', ' ').' Ko',
                        'label' => $f->state->label(), 'clean' => $f->state === FileState::Clean, 'rejected' => $f->state === FileState::Rejected,
                        'url' => $f->state === FileState::Clean ? URL::temporarySignedRoute('messages.files.download', now()->addMinutes(5), ['file' => $f->getKey(), 'u' => $u->getKey()]) : null,
                    ] : null,
                ];
            })->all(),
        ];
    }

    /** Nombre de messages postérieurs à $afterId (actualisation légère, sans rien marquer comme lu). */
    public function newerThan(User $u, string $conversationId, int $afterId): int
    {
        $c = Conversation::query()->whereKey($conversationId)->first();
        if ($c === null || ! $c->isParticipant($u)) {
            return 0;
        }

        return Message::query()->where('conversation_id', $c->getKey())->where('id', '>', $afterId)->where('sender_id', '!=', $u->getKey())->count();
    }
}
