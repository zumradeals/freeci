<?php

namespace App\Modules\Accounts\Actions;

use App\Modules\Accounts\Exceptions\AccountRestricted;
use App\Modules\Accounts\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Régime d'un compte suspendu. La suspension RESTREINT LES NOUVELLES ACTIVITÉS (nouvelle demande, nouvelle mission, nouvelle proposition,
 * soumission au contrôle, acceptation d'une nouvelle demande, nouvelle conversation) ; elle ne touche jamais aux commandes et obligations
 * en cours (paiement, brief, livraison, corrections, report, validation, messages d'une commande active), qui se poursuivent normalement.
 */
final class AccountStanding
{
    public static function assertCanStartNew(User $user): void
    {
        if (self::suspended($user->getKey())) {
            throw new AccountRestricted;
        }
    }

    public static function suspended(string $userId): bool
    {
        return DB::table('users')->where('id', $userId)->whereNotNull('suspended_at')->exists();
    }
}
