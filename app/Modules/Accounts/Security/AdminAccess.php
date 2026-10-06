<?php

namespace App\Modules\Accounts\Security;

use App\Modules\Accounts\Models\User;
use Illuminate\Contracts\Session\Session;

/**
 * Conditions d'accès à l'administration, évaluées CÔTÉ SERVEUR à chaque requête (jamais déduites d'un état conservé côté navigateur) :
 * habilitation en vigueur, adresse vérifiée, double authentification activée, et double authentification franchie dans CETTE session.
 * La confirmation récente d'identité (acte sensible) est une condition distincte.
 */
final class AdminAccess
{
    /** @return array{grant: bool, email: bool, mfa: bool, session: bool} */
    public function state(User $user, Session $session): array
    {
        return [
            'grant' => $user->isStaff(),
            'email' => $user->emailVerified(),
            'mfa' => $user->hasTwoFactor(),
            'session' => $this->mfaPassed($user, $session),
        ];
    }

    public function ready(User $user, Session $session): bool
    {
        return ! in_array(false, $this->state($user, $session), true);
    }

    public function markMfaPassed(User $user, Session $session): void
    {
        $session->put('admin_mfa', ['user' => $user->getKey(), 'at' => time()]);
        $this->markRecentlyConfirmed($user, $session);
    }

    public function mfaPassed(User $user, Session $session): bool
    {
        $m = $session->get('admin_mfa');

        return is_array($m) && ($m['user'] ?? null) === $user->getKey() && ($m['at'] ?? 0) > time() - (int) config('freeci.admin.mfa_session_minutes') * 60;
    }

    public function markRecentlyConfirmed(User $user, Session $session): void
    {
        $session->put('admin_reauth', ['user' => $user->getKey(), 'at' => time()]);
    }

    public function recentlyConfirmed(User $user, Session $session): bool
    {
        $m = $session->get('admin_reauth');

        return is_array($m) && ($m['user'] ?? null) === $user->getKey() && ($m['at'] ?? 0) > time() - (int) config('freeci.admin.reauth_minutes') * 60;
    }

    public function forget(Session $session): void
    {
        $session->forget(['admin_mfa', 'admin_reauth']);
    }
}
