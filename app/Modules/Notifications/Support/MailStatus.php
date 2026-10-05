<?php

namespace App\Modules\Notifications\Support;

/**
 * Le courrier est-il réellement configuré ? Les pilotes « log » et « array » n'envoient RIEN : on ne présente jamais un courriel
 * journalisé comme envoyé. Même un pilote réel ne garantit pas la remise en boîte : « envoyé » = accepté par le serveur SMTP configuré.
 */
final class MailStatus
{
    public static function deliverable(): bool
    {
        return (bool) config('freeci.notifications.emails') && ! in_array((string) config('mail.default'), ['log', 'array', ''], true);
    }
}
