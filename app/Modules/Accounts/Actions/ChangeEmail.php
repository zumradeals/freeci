<?php

namespace App\Modules\Accounts\Actions;

use App\Modules\Accounts\Exceptions\AccountConflict;
use App\Modules\Accounts\Models\User;
use App\Modules\Accounts\Security\SecurityLog;
use App\Modules\Accounts\Support\AccountMail;
use App\Modules\Notifications\Support\MailStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Changement d'adresse : l'ancienne adresse reste active tant que la NOUVELLE n'a pas été vérifiée par son lien (signé, à usage unique, borné dans le temps).
 * L'ancienne adresse est prévenue à la demande et à la confirmation. Sans courrier réel configuré, le changement est refusé : rien n'est « vérifié » sans lien reçu.
 */
final class ChangeEmail
{
    public function __construct(private AccountSettings $settings) {}

    public function request(User $user, string $newEmail, string $password): void
    {
        $this->settings->assertPassword($user, $password);
        $new = mb_strtolower(trim($newEmail));
        if (! filter_var($new, FILTER_VALIDATE_EMAIL) || mb_strlen($new) > 254) {
            throw ValidationException::withMessages(['email' => 'Adresse e-mail invalide.']);
        }
        if ($new === mb_strtolower($user->email)) {
            throw ValidationException::withMessages(['email' => 'C’est déjà votre adresse actuelle.']);
        }
        if (! MailStatus::configured()) {
            throw new AccountConflict('Le courrier n’est pas configuré sur ce serveur : la nouvelle adresse ne peut pas être vérifiée, votre adresse actuelle est conservée.');
        }
        if (DB::table('users')->whereRaw('lower(email) = ?', [$new])->exists()) {
            // Même réponse qu'un succès : on ne révèle pas si l'adresse appartient déjà à un compte.
            SecurityLog::record('email_change_collision', $user->getKey());

            return;
        }
        $token = Str::random(48);
        $id = (string) Str::uuid();
        DB::transaction(function () use ($user, $new, $token, $id) {
            DB::table('email_change_requests')->where('user_id', $user->getKey())->where('state', 'pending')->update(['state' => 'superseded']);
            DB::table('email_change_requests')->insert(['id' => $id, 'user_id' => $user->getKey(), 'new_email' => $new, 'token_hash' => hash('sha256', $token), 'state' => 'pending',
                'expires_at' => now()->addMinutes((int) config('freeci.account.email_change_minutes')), 'created_at' => now()]);
        });
        $url = URL::temporarySignedRoute('account.email.confirm', now()->addMinutes((int) config('freeci.account.email_change_minutes')), ['id' => $id, 'token' => $token]);
        if (! AccountMail::send($new, 'confirmez votre nouvelle adresse e-mail', ['Une demande de remplacement de l’adresse de votre compte FreeCI par celle-ci a été faite. Ouvrez ce lien (connecté à votre compte) pour la confirmer. Il expire dans '.config('freeci.account.email_change_minutes').' minutes.'], $url)) {
            DB::table('email_change_requests')->where('id', $id)->update(['state' => 'superseded']);

            throw new AccountConflict('L’envoi du courriel de vérification a échoué : votre adresse actuelle est conservée. Réessayez plus tard.');
        }
        AccountMail::send($user->email, 'demande de changement d’adresse e-mail', ['Un changement de l’adresse e-mail de votre compte a été demandé. Votre adresse actuelle reste active tant que la nouvelle n’est pas confirmée.']);
        SecurityLog::record('email_change_requested', $user->getKey());
    }

    /** @return 'ok'|'invalid'|'taken' */
    public function confirm(User $user, string $id, string $token): string
    {
        $r = DB::table('email_change_requests')->where('id', $id)->where('user_id', $user->getKey())->where('state', 'pending')->first();
        if ($r === null || $r->expires_at < now() || ! hash_equals($r->token_hash, hash('sha256', $token))) {
            SecurityLog::record('email_change_rejected', $user->getKey());

            return 'invalid';
        }
        $old = $user->email;
        $status = DB::transaction(function () use ($user, $r) {
            if (DB::table('users')->whereRaw('lower(email) = ?', [$r->new_email])->where('id', '<>', $user->getKey())->exists()) {
                DB::table('email_change_requests')->where('id', $r->id)->update(['state' => 'superseded']);

                return 'taken';
            }
            DB::table('users')->where('id', $user->getKey())->update(['email' => $r->new_email, 'email_verified_at' => now(), 'updated_at' => now()]);
            DB::table('email_change_requests')->where('id', $r->id)->update(['state' => 'confirmed', 'confirmed_at' => now()]);

            return 'ok';
        });
        if ($status === 'ok') {
            SecurityLog::record('email_changed', $user->getKey());
            AccountMail::send($old, 'adresse e-mail remplacée', ['L’adresse e-mail de votre compte FreeCI a été remplacée par une nouvelle adresse vérifiée. Cette adresse ne sera plus utilisée.']);
        }

        return $status;
    }

    public function pending(User $user): ?object
    {
        return DB::table('email_change_requests')->where('user_id', $user->getKey())->where('state', 'pending')->where('expires_at', '>', now())->first(['new_email', 'expires_at']);
    }
}
