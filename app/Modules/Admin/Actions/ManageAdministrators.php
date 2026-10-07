<?php

namespace App\Modules\Admin\Actions;

use App\Modules\Accounts\Actions\GrantAdministrator;
use App\Modules\Accounts\Actions\RevokeAdministrator;
use App\Modules\Accounts\Models\StaffGrant;
use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Exceptions\ModerationDenied;
use Carbon\CarbonInterface;

/**
 * Habilitation d'administrateur depuis l'administration, sous garde-fous : administrateur en vigueur avec double authentification, identité reconfirmée,
 * phrase de confirmation exacte, motif, compte EXISTANT à adresse vérifiée et non suspendu. On ne peut jamais retirer sa propre habilitation ni la dernière.
 * Chaque octroi ou retrait est journalisé (journal d'audit et journal de sécurité du compte visé).
 */
final class ManageAdministrators
{
    public const PHRASE = 'DONNER LES DROITS D’ADMINISTRATEUR';

    public function __construct(private AdminAudit $audit, private GrantAdministrator $grants, private RevokeAdministrator $revokes) {}

    /** @return list<array<string, mixed>> */
    public function members(): array
    {
        return StaffGrant::query()->active()->where('capability', StaffGrant::ADMINISTRATOR)->with('user')->orderBy('granted_at')->get()->map(fn (StaffGrant $g) => [
            'userId' => $g->user_id, 'name' => $g->user->name, 'email' => $g->user->email, 'since' => $g->granted_at?->timezone('Africa/Abidjan')->format('d/m/Y'),
            'until' => $g->expires_at?->timezone('Africa/Abidjan')->format('d/m/Y'), 'reason' => $g->reason, 'by' => str_starts_with((string) $g->granted_by, 'admin:') ? 'un administrateur' : 'la console du serveur',
            'ready' => $g->user->emailVerified() && $g->user->hasTwoFactor(),
        ])->all();
    }

    public function grant(User $admin, string $email, string $reason, ?CarbonInterface $expiresAt, string $phrase): void
    {
        if ($this->normalize($phrase) !== $this->normalize(self::PHRASE)) {
            throw new ModerationDenied('Pour accorder ce pouvoir, saisissez exactement la phrase : '.self::PHRASE);
        }
        $user = User::query()->where('email', mb_strtolower(trim($email)))->first();
        $this->audit->run($admin, 'admin.grant', 'user', $user?->getKey(), $user?->email ?? $email, $reason, function () use ($admin, $user, $reason, $expiresAt) {
            if ($user === null) {
                throw new ModerationDenied('Aucun compte avec cette adresse : la personne doit d’abord s’inscrire.');
            }
            if (! $user->emailVerified()) {
                throw new ModerationDenied('L’adresse de ce compte n’est pas vérifiée : la personne doit d’abord confirmer son adresse e-mail.');
            }
            if ($user->isSuspended()) {
                throw new ModerationDenied('Ce compte est suspendu.');
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
        $this->audit->run($admin, 'admin.revoke', 'user', $userId, $user->email, $reason, function () use ($admin, $user, $reason) {
            if ($user->getKey() === $admin->getKey()) {
                throw new ModerationDenied('Vous ne pouvez pas retirer votre propre habilitation : demandez à un autre administrateur, ou passez par la console du serveur.');
            }
            if (StaffGrant::query()->active()->where('capability', StaffGrant::ADMINISTRATOR)->count() <= 1) {
                throw new ModerationDenied('Il doit toujours rester au moins un administrateur.');
            }
            ($this->revokes)($user, $reason, 'admin:'.$admin->getKey());
        });
    }

    private function normalize(string $s): string
    {
        return mb_strtoupper(trim(str_replace(['’', '‘', '`'], "'", $s)));
    }
}
