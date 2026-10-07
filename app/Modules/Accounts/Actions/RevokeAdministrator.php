<?php

namespace App\Modules\Accounts\Actions;

use App\Modules\Accounts\Models\StaffGrant;
use App\Modules\Accounts\Models\User;
use App\Modules\Accounts\Security\SecurityLog;
use Illuminate\Support\Facades\Log;

final class RevokeAdministrator
{
    /** @return int nombre d'habilitations révoquées (les rôles client et freelance sont conservés) */
    public function __invoke(User $user, string $reason, string $by = 'console'): int
    {
        $n = $user->staffGrants()->active()->where('capability', StaffGrant::ADMINISTRATOR)
            ->update(['revoked_at' => now(), 'revoked_reason' => $reason]);
        SecurityLog::record('admin_revoked', $user->id, ['by' => $by, 'reason' => $reason]);
        Log::info('Habilitation administrateur révoquée', ['user_id' => $user->id, 'count' => $n]);

        return $n;
    }
}
