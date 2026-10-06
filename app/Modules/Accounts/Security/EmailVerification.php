<?php

namespace App\Modules\Accounts\Security;

use App\Mail\VerifyEmailMail;
use App\Modules\Accounts\Models\User;
use App\Modules\Notifications\Support\MailStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Vérification de l'adresse par courriel. Une adresse n'est « vérifiée » que lorsque son titulaire a ouvert le lien signé reçu.
 * Un courriel écrit dans les journaux (pilote « log ») n'est JAMAIS un courriel envoyé : sans courrier réel, l'envoi est « indisponible ».
 */
final class EmailVerification
{
    /** @return 'already'|'sent'|'unavailable'|'failed' — « sent » = accepté par le serveur de courrier configuré, pas remis en boîte */
    public function send(User $user): string
    {
        if ($user->emailVerified()) {
            return 'already';
        }
        if (! MailStatus::configured()) {
            SecurityLog::record('email_verification_unavailable', $user->getKey());

            return 'unavailable';
        }
        try {
            $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->getKey(), 'hash' => $this->hash($user)]);
            Mail::to($user->email)->send(new VerifyEmailMail($url));
        } catch (Throwable $e) {
            report($e);
            SecurityLog::record('email_verification_failed', $user->getKey());

            return 'failed';
        }
        SecurityLog::record('email_verification_sent', $user->getKey());

        return 'sent';
    }

    /** Le lien signé a déjà été contrôlé (signature, expiration) ; on revérifie que l'empreinte correspond à l'adresse ACTUELLE. */
    public function confirm(User $user, string $hash): bool
    {
        if (! hash_equals($this->hash($user), $hash)) {
            SecurityLog::record('email_verification_rejected', $user->getKey());

            return false;
        }
        if (! $user->emailVerified()) {
            DB::table('users')->where('id', $user->getKey())->update(['email_verified_at' => now()]);
            SecurityLog::record('email_verified', $user->getKey(), ['channel' => 'link']);
        }

        return true;
    }

    /** Attestation explicite par la console (porteur, accès serveur) : journalisée comme telle, jamais confondue avec un lien ouvert. */
    public function attestByConsole(User $user, string $reason): void
    {
        DB::table('users')->where('id', $user->getKey())->update(['email_verified_at' => now()]);
        SecurityLog::record('email_verified', $user->getKey(), ['channel' => 'console', 'reason' => $reason, 'by' => 'console']);
    }

    private function hash(User $user): string
    {
        return sha1(mb_strtolower($user->email));
    }
}
