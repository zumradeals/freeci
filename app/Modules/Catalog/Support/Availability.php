<?php

namespace App\Modules\Catalog\Support;

use Illuminate\Support\Carbon;

/**
 * Règle UNIQUE de disponibilité d'un freelance. Indisponible = `unavailable_at` renseigné ET non revenu : avec `auto_reopen`, la date de retour (`back_on`) réouvre ce jour-là
 * (évaluée à la lecture, en PHP comme en SQL : même règle, aucune dépendance à la tâche planifiée). Jamais déduite d'autre chose.
 */
final class Availability
{
    /** Fragment SQL « ce profil est indisponible maintenant » (alias de la table freelance_profiles). */
    public static function unavailableSql(string $alias = 'p'): string
    {
        return "({$alias}.unavailable_at IS NOT NULL AND NOT ({$alias}.auto_reopen AND {$alias}.back_on IS NOT NULL AND {$alias}.back_on <= CURRENT_DATE))";
    }

    /** @param  object|array<string, mixed>  $profile */
    public static function unavailable(object|array $profile, ?Carbon $today = null): bool
    {
        $g = fn (string $k) => is_array($profile) ? ($profile[$k] ?? null) : ($profile->{$k} ?? null);
        if ($g('unavailable_at') === null) {
            return false;
        }
        $back = $g('back_on');
        if ($g('auto_reopen') && $back !== null && Carbon::parse($back)->startOfDay()->lte(($today ?? now())->copy()->startOfDay())) {
            return false;
        }

        return true;
    }
}
