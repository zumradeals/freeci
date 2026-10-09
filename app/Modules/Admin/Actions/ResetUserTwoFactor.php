<?php

namespace App\Modules\Admin\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Accounts\Security\TwoFactor;
use App\Modules\Notifications\Actions\Notify;
use Illuminate\Support\Facades\DB;

/**
 * Réinitialisation de la double authentification d'une PERSONNE (client ou freelance) qui a perdu son téléphone ET ses codes de secours : jamais automatique.
 * Motif obligatoire (journal d'audit), confirmation récente d'identité exigée par la route, toutes les sessions de la personne fermées, la personne en est
 * informée (application et courriel si configuré) avec le motif. Jamais sur son propre compte ni sur un compte du personnel (procédure console).
 * L'identité de la personne est vérifiée PAR l'équipe avant cet acte : FreeCI n'automatise aucune règle de vérification.
 */
final class ResetUserTwoFactor
{
    public function __construct(private AdminAudit $audit, private TwoFactor $mfa, private Notify $notify) {}

    public function __invoke(User $admin, string $userId, string $reason): void
    {
        $label = DB::table('users')->where('id', $userId)->value('name');
        $this->audit->run($admin, 'user.mfa_reset', 'user', $userId, $label, $reason, function () use ($admin, $userId, $reason) {
            $reason = trim($reason);
            if (mb_strlen($reason) < 10 || mb_strlen($reason) > 1000) {
                throw new \DomainException('Le motif est obligatoire (10 à 1000 caractères) : indiquez comment l’identité a été vérifiée. Il est conservé dans le journal et communiqué à la personne.');
            }
            if ($userId === $admin->getKey()) {
                throw new \DomainException('Vous ne pouvez pas réinitialiser votre propre double authentification ici.');
            }
            $user = User::query()->whereKey($userId)->first();
            if ($user === null) {
                throw new \DomainException('Compte introuvable.');
            }
            if ($user->isStaff()) {
                throw new \DomainException('La double authentification d’un compte du personnel se réinitialise depuis le serveur (procédure console journalisée).');
            }
            if (! $user->hasTwoFactor()) {
                throw new \DomainException('Ce compte n’a pas activé la double authentification.');
            }
            DB::transaction(function () use ($user, $admin, $reason) {
                $this->mfa->reset($user, 'admin:'.$admin->getKey());           // secret, codes et TOUTES les sessions
                ($this->notify)($user->getKey(), 'two_factor_reset', 'two_factor_reset:'.$user->getKey().':'.now()->timestamp, 'La double authentification de votre compte a été réinitialisée', $reason.' Si vous n’êtes pas à l’origine de cette demande, changez votre mot de passe et contactez l’équipe.', 'account.settings');
            });
        });
    }
}
