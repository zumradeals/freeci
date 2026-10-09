<?php

namespace App\Modules\Accounts\Security;

use App\Modules\Accounts\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Deuxième facteur pour les ACTES SENSIBLES du compte (changer le mot de passe ou l'adresse, déclarer des coordonnées de versement, régénérer les codes, désactiver) :
 * quand la double authentification est active, un code valide s'ajoute au mot de passe. Sans double authentification, aucune exigence supplémentaire.
 * Les tentatives passent par `TwoFactor` : même limitation et même journal que la connexion.
 */
final class SecondFactorGate
{
    public function __construct(private TwoFactor $mfa) {}

    public function active(User $user): bool
    {
        return $user->hasTwoFactor();
    }

    /** @throws ValidationException */
    public function assert(User $user, ?string $code, string $field = 'code'): void
    {
        if (! $user->hasTwoFactor()) {
            return;
        }
        $code = trim((string) $code);
        if ($code === '') {
            throw ValidationException::withMessages([$field => 'Saisissez le code à 6 chiffres de votre application d’authentification (ou un code de secours).']);
        }
        if ($this->mfa->locked($user)) {
            throw ValidationException::withMessages([$field => 'Trop de tentatives : réessayez dans '.max(1, (int) ceil($this->mfa->secondsLocked($user) / 60)).' minute(s).']);
        }
        if (! $this->mfa->challenge($user, $code)) {
            throw ValidationException::withMessages([$field => 'Code invalide ou déjà utilisé. Un code ne sert qu’une fois : attendez le prochain code de l’application.']);
        }
    }
}
