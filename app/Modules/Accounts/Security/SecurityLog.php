<?php

namespace App\Modules\Accounts\Security;

use Illuminate\Support\Facades\DB;

/**
 * Journal des événements de sécurité (ajout seul). Seules des clés d'une liste blanche sont conservées : jamais de mot de passe,
 * de secret, de code MFA ou de code de récupération, ni d'adresse e-mail saisie (on garde l'identifiant du compte quand il existe).
 */
final class SecurityLog
{
    private const META_KEYS = ['channel', 'reason', 'result', 'remaining', 'purpose', 'grant', 'by'];

    public static function record(string $type, ?string $userId = null, array $meta = [], ?string $ip = null): void
    {
        $meta = array_intersect_key($meta, array_flip(self::META_KEYS));
        $meta = array_map(fn ($v) => is_scalar($v) ? mb_substr((string) $v, 0, 200) : null, $meta);
        DB::table('security_events')->insert([
            'user_id' => $userId, 'type' => $type, 'ip' => $ip ?? (app()->runningInConsole() ? null : request()->ip()),
            'meta' => $meta === [] ? null : json_encode($meta), 'created_at' => now(),
        ]);
    }
}
