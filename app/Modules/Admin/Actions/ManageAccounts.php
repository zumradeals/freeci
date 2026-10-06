<?php

namespace App\Modules\Admin\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Exceptions\ModerationDenied;
use App\Modules\Notifications\Actions\Notify;
use Illuminate\Support\Facades\DB;

/**
 * Suspension et réactivation de comptes, motivées et historisées. La suspension restreint les NOUVELLES activités (voir AccountStanding) ;
 * elle ne supprime rien et n'interrompt aucune commande en cours. Un administrateur en vigueur ne peut pas être suspendu ici
 * (retirer d'abord son habilitation par la console), ni se suspendre lui-même.
 */
final class ManageAccounts
{
    public function __construct(private AdminAudit $audit, private Notify $notify) {}

    public function suspend(User $admin, string $userId, string $reason): void
    {
        $label = DB::table('users')->where('id', $userId)->value('name');
        $this->audit->run($admin, 'user.suspend', 'user', $userId, $label, $reason, function () use ($admin, $userId, $reason) {
            $reason = $this->reason($reason);
            DB::transaction(function () use ($admin, $userId, $reason) {
                $u = User::query()->whereKey($userId)->lockForUpdate()->first() ?? throw new \DomainException('Compte introuvable.');
                if ($u->getKey() === $admin->getKey()) {
                    throw new ModerationDenied('Vous ne pouvez pas suspendre votre propre compte.');
                }
                if ($u->isAdministrator()) {
                    throw new ModerationDenied('Ce compte porte une habilitation d’administrateur : retirez-la d’abord par la console.');
                }
                if ($u->isSuspended()) {
                    throw new \DomainException('Ce compte est déjà suspendu.');
                }
                DB::table('users')->where('id', $u->getKey())->update(['suspended_at' => now()]);
                $id = DB::table('account_restrictions')->insertGetId(['user_id' => $u->getKey(), 'action' => 'suspended', 'reason' => $reason, 'actor_id' => $admin->getKey(), 'created_at' => now()]);
                ($this->notify)($u->getKey(), 'account_status', 'account_restriction:'.$id, 'Votre compte est suspendu : plus de nouvelle activité, vos commandes en cours se poursuivent', $reason, 'account.dashboard');
            });
        });
    }

    public function reactivate(User $admin, string $userId, string $reason): void
    {
        $label = DB::table('users')->where('id', $userId)->value('name');
        $this->audit->run($admin, 'user.reactivate', 'user', $userId, $label, $reason, function () use ($admin, $userId, $reason) {
            $reason = $this->reason($reason);
            DB::transaction(function () use ($admin, $userId, $reason) {
                $u = User::query()->whereKey($userId)->lockForUpdate()->first() ?? throw new \DomainException('Compte introuvable.');
                if (! $u->isSuspended()) {
                    throw new \DomainException('Ce compte n’est pas suspendu.');
                }
                DB::table('users')->where('id', $u->getKey())->update(['suspended_at' => null]);
                $id = DB::table('account_restrictions')->insertGetId(['user_id' => $u->getKey(), 'action' => 'reactivated', 'reason' => $reason, 'actor_id' => $admin->getKey(), 'created_at' => now()]);
                ($this->notify)($u->getKey(), 'account_status', 'account_restriction:'.$id, 'Votre compte est réactivé : vous pouvez de nouveau démarrer de nouvelles activités', null, 'account.dashboard');
            });
        });
    }

    private function reason(string $reason): string
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 1000) {
            throw new \DomainException('Le motif est obligatoire (10 à 1000 caractères) : il est conservé dans l’historique.');
        }

        return $reason;
    }
}
