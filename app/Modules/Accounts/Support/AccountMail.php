<?php

namespace App\Modules\Accounts\Support;

use App\Mail\AccountNoticeMail;
use App\Modules\Notifications\Support\MailStatus;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Envoi d'un avis de compte. Sans courrier réel, rien n'est envoyé (jamais présenté comme envoyé) ; renvoie true seulement si le serveur de courrier a accepté. */
final class AccountMail
{
    /** @param list<string> $lines */
    public static function send(string $to, string $subject, array $lines, ?string $url = null): bool
    {
        if (! MailStatus::configured()) {
            return false;
        }
        try {
            Mail::to($to)->send(new AccountNoticeMail($subject, $lines, $url));
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        return true;
    }
}
