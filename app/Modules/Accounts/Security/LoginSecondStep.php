<?php

namespace App\Modules\Accounts\Security;

use Illuminate\Contracts\Session\Session;

/**
 * Connexion en deux temps : le mot de passe correct ne OUVRE PAS la session d'une personne qui a activé la double authentification. Il ouvre seulement une
 * étape en attente (identifiant de la personne, heure), liée à CETTE session de navigateur et limitée dans le temps ; la session authentifiée n'est créée
 * qu'après un code valide. Aucune connexion mémorisée n'est jamais posée pour ces comptes.
 */
final class LoginSecondStep
{
    private const KEY = 'login_2fa';

    public const MINUTES = 10;

    public function begin(Session $session, string|int $userId): void
    {
        $session->put(self::KEY, ['user' => (string) $userId, 'at' => time()]);
    }

    public function pendingUserId(Session $session): ?string
    {
        $p = $session->get(self::KEY);
        if (! is_array($p) || ($p['at'] ?? 0) < time() - self::MINUTES * 60 || ! is_string($p['user'] ?? null)) {
            $session->forget(self::KEY);

            return null;
        }

        return $p['user'];
    }

    public function clear(Session $session): void
    {
        $session->forget(self::KEY);
    }
}
