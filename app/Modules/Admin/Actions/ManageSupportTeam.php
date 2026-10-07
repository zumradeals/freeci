<?php

namespace App\Modules\Admin\Actions;

use App\Modules\Accounts\Actions\GrantSupport;
use App\Modules\Accounts\Models\StaffGrant;
use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Exceptions\ModerationDenied;
use Carbon\CarbonInterface;

/**
 * Équipe d'assistance gérée depuis l'administration : l'administrateur (double authentification + identité reconfirmée) accorde ou retire l'habilitation
 * « support » à un compte EXISTANT. Cette habilitation ne donne ni modération, ni gestion de comptes, ni audit, ni opération financière. L'octroi
 * de l'habilitation d'administrateur reste volontairement hors de l'interface (console).
 */
final class ManageSupportTeam
{
    public function __construct(private AdminAudit $audit, private GrantSupport $grants) {}

    /** @return list<array<string, mixed>> */
    public function members(): array
    {
        return StaffGrant::query()->active()->where('capability', StaffGrant::SUPPORT)->with('user')->orderBy('granted_at')->get()->map(fn (StaffGrant $g) => [
            'userId' => $g->user_id, 'name' => $g->user->name, 'email' => $g->user->email, 'since' => $g->granted_at?->timezone('Africa/Abidjan')->format('d/m/Y'),
            'until' => $g->expires_at?->timezone('Africa/Abidjan')->format('d/m/Y'), 'reason' => $g->reason,
            'ready' => $g->user->emailVerified() && $g->user->hasTwoFactor(),
        ])->all();
    }

    public function grant(User $admin, string $email, string $reason, ?CarbonInterface $expiresAt): void
    {
        $user = User::query()->where('email', mb_strtolower(trim($email)))->first();
        $this->audit->run($admin, 'support.grant', 'user', $user?->getKey(), $user?->email ?? $email, $reason, function () use ($admin, $user, $reason, $expiresAt) {
            if ($user === null) {
                throw new ModerationDenied('Aucun compte avec cette adresse : la personne doit d’abord s’inscrire.');
            }
            if ($user->isAdministrator()) {
                throw new ModerationDenied('Ce compte est déjà administrateur.');
            }
            if ($expiresAt !== null && $expiresAt->isPast()) {
                throw new ModerationDenied('La date de fin doit être dans le futur.');
            }
            ($this->grants)($user, $reason, $expiresAt, 'admin:'.$admin->getKey());
        });
    }

    public function revoke(User $admin, string $userId, string $reason): void
    {
        $user = User::query()->findOrFail($userId);
        $this->audit->run($admin, 'support.revoke', 'user', $userId, $user->email, $reason, function () use ($admin, $user, $reason) {
            $this->grants->revoke($user, $reason, 'admin:'.$admin->getKey());
        });
    }
}
