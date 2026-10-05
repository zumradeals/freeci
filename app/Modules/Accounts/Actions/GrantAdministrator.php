<?php

namespace App\Modules\Accounts\Actions;

use App\Modules\Accounts\Models\AccountRole;
use App\Modules\Accounts\Models\StaffGrant;
use App\Modules\Accounts\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Désigne un administrateur : habilitation motivée et datée + accès aux trois espaces (client, freelance, administration).
 * Idempotente : une habilitation déjà active n'est pas dupliquée.
 */
final class GrantAdministrator
{
    public function __invoke(User $user, string $reason, ?CarbonInterface $expiresAt = null, string $by = 'console'): StaffGrant
    {
        return DB::transaction(function () use ($user, $reason, $expiresAt, $by) {
            foreach ([AccountRole::CLIENT, AccountRole::FREELANCE] as $role) {
                $user->roles()->firstOrCreate(['role' => $role]);
            }

            $grant = $user->staffGrants()->active()->where('capability', StaffGrant::ADMINISTRATOR)->first()
                ?? $user->staffGrants()->create([
                    'capability' => StaffGrant::ADMINISTRATOR,
                    'reason' => $reason,
                    'granted_by' => $by,
                    'expires_at' => $expiresAt,
                ]);

            Log::info('Habilitation administrateur accordée', ['user_id' => $user->id, 'grant_id' => $grant->id, 'by' => $by]);

            return $grant;
        });
    }
}
