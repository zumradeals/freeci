<?php

namespace App\Modules\Accounts\Actions;

use Illuminate\Support\Facades\Password;

/**
 * Demande de récupération. Le résultat est volontairement indistinguable que l'adresse existe ou non
 * (aucune énumération de comptes) ; l'envoi lui-même est limité par le courtier de mots de passe.
 */
final class SendPasswordResetLink
{
    public function __invoke(string $email): void
    {
        Password::sendResetLink(['email' => mb_strtolower(trim($email))]);
    }
}
