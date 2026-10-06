<?php

namespace App\Modules\Messaging\Actions;

use App\Modules\Accounts\Actions\AccountStanding;
use App\Modules\Messaging\Models\ContactBlock;
use App\Modules\Messaging\Models\Conversation;
use App\Modules\Orders\Enums\OrderState;
use Illuminate\Support\Facades\DB;

/**
 * Règles d'envoi. Le blocage d'un contact suspend les NOUVEAUX échanges non nécessaires ; il ne supprime rien (l'historique reste lisible)
 * et ne gêne jamais les échanges indispensables à une commande ACTIVE (rattachée à la conversation, ni terminée, ni annulée, ni expirée).
 */
final class ConversationRules
{
    public static function blockedBetween(string $a, string $b): bool
    {
        return ContactBlock::query()->where(fn ($q) => $q->where('blocker_id', $a)->where('blocked_id', $b))->orWhere(fn ($q) => $q->where('blocker_id', $b)->where('blocked_id', $a))->exists();
    }

    public static function essential(Conversation $c): bool
    {
        if ($c->order_id === null) {
            return false;
        }
        $state = DB::table('orders')->where('id', $c->order_id)->value('state');          // valeur brute : les casts d'Eloquent ne s'appliquent pas ici

        return $state !== null && ! in_array($state, [OrderState::Cancelled->value, OrderState::Expired->value, OrderState::Closed->value], true);
    }

    /** @return bool vrai si un nouvel échange est possible */
    public static function canSend(Conversation $c): bool
    {
        if (self::essential($c)) {
            return true;         // commande active : les échanges indispensables restent possibles (blocage ou suspension)
        }

        return ! self::blockedBetween($c->client_id, $c->freelancer_id) && ! AccountStanding::suspended($c->client_id) && ! AccountStanding::suspended($c->freelancer_id);
    }
}
