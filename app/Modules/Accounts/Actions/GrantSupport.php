<?php

namespace App\Modules\Accounts\Actions;

use App\Modules\Accounts\Models\StaffGrant;
use App\Modules\Accounts\Models\User;
use App\Modules\Accounts\Security\SecurityLog;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/** Habilitation « support » (console ou administration, par l'administrateur) : motivée, datée, révocable. Ne donne ni modération, ni gestion de comptes, ni audit. */
final class GrantSupport
{
    public function __invoke(User $user, string $reason, ?CarbonInterface $expiresAt = null, string $by = 'console'): StaffGrant
    {
        return DB::transaction(function () use ($user, $reason, $expiresAt, $by) {
            $grant = $user->staffGrants()->active()->where('capability', StaffGrant::SUPPORT)->first()
                ?? $user->staffGrants()->create(['capability' => StaffGrant::SUPPORT, 'reason' => $reason, 'granted_by' => $by, 'expires_at' => $expiresAt]);
            SecurityLog::record('support_granted', $user->getKey(), ['by' => $by, 'reason' => $reason]);

            return $grant;
        });
    }

    public function revoke(User $user, string $reason, string $by = 'console'): int
    {
        $n = $user->staffGrants()->active()->where('capability', StaffGrant::SUPPORT)->update(['revoked_at' => now(), 'revoked_reason' => $reason]);
        SecurityLog::record('support_revoked', $user->getKey(), ['by' => $by, 'reason' => $reason]);
        // Les affectations en cours prennent fin avec l'habilitation : plus d'accès aux dossiers.
        DB::table('support_cases')->where('assignee_id', $user->getKey())->whereNotIn('status', ['decided', 'closed'])
            ->update(['assignee_id' => null, 'access_reason' => null, 'access_opened_at' => null, 'updated_at' => now()]);

        return $n;
    }
}
