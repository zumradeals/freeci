<?php

namespace App\Modules\Accounts\Actions;

use App\Modules\Accounts\Exceptions\AccountConflict;
use App\Modules\Accounts\Models\User;
use App\Modules\Accounts\Security\SecurityLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Informations personnelles, mot de passe et sessions. Toute opération sensible exige le mot de passe actuel. */
final class AccountSettings
{
    public function updateName(User $user, string $name): void
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            throw ValidationException::withMessages(['name' => 'Indiquez votre nom (2 à 100 caractères).']);
        }
        DB::table('users')->where('id', $user->getKey())->update(['name' => $name, 'updated_at' => now()]);
        SecurityLog::record('profile_name_changed', $user->getKey());
    }

    public function assertPassword(User $user, string $password, string $field = 'current_password'): void
    {
        if ($password === '' || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([$field => 'Mot de passe actuel incorrect.']);
        }
    }

    /** Remplace le mot de passe et déconnecte TOUTES les autres sessions. */
    public function changePassword(User $user, string $current, string $new, string $currentSessionId): void
    {
        $this->assertPassword($user, $current);
        if (Hash::check($new, $user->password)) {
            throw ValidationException::withMessages(['password' => 'Le nouveau mot de passe doit différer de l’actuel.']);
        }
        $user->forceFill(['password' => $new, 'remember_token' => Str::random(60)])->save();
        $this->revokeOthers($user, $currentSessionId);
        SecurityLog::record('password_changed', $user->getKey());
    }

    /** @return list<array{id: string, current: bool, device: string, ip: ?string, last: Carbon}> */
    public function sessions(User $user, string $currentSessionId): array
    {
        return DB::table('sessions')->where('user_id', $user->getKey())->orderByDesc('last_activity')->get(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(fn ($s) => ['id' => $s->id, 'current' => $s->id === $currentSessionId, 'device' => self::device((string) $s->user_agent), 'ip' => $s->ip_address, 'last' => Carbon::createFromTimestamp($s->last_activity)])->all();
    }

    public function revoke(User $user, string $sessionId, string $currentSessionId): void
    {
        if ($sessionId === $currentSessionId) {
            throw new AccountConflict('Pour fermer cette session, utilisez « Se déconnecter ».');
        }
        DB::table('sessions')->where('id', $sessionId)->where('user_id', $user->getKey())->delete();       // jamais la session d'un autre compte
        SecurityLog::record('session_revoked', $user->getKey());
    }

    public function revokeOthers(User $user, string $currentSessionId): int
    {
        $n = DB::table('sessions')->where('user_id', $user->getKey())->where('id', '<>', $currentSessionId)->delete();
        SecurityLog::record('sessions_revoked', $user->getKey(), ['result' => (string) $n]);

        return $n;
    }

    private static function device(string $ua): string
    {
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge', str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera', str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Chrome/') => 'Chrome', str_contains($ua, 'Safari/') => 'Safari', default => 'Navigateur',
        };
        $os = match (true) {
            str_contains($ua, 'Android') => 'Android', str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS', str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS') => 'macOS', str_contains($ua, 'Linux') => 'Linux', default => 'système inconnu',
        };

        return "{$browser} · {$os}";
    }
}
