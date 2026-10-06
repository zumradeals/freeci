<?php

namespace App\Modules\Admin\Settings;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Applique les valeurs saisies en administration par-dessus la configuration du code / du .env. Appelé au démarrage de chaque requête :
 * une seule lecture (mise en cache, invalidée à chaque écriture). Table absente (avant migration) ou base indisponible : on ignore, rien ne casse.
 */
final class AppSettings
{
    private const CACHE_KEY = 'app_settings.v1';

    /** @var array<string, mixed> valeurs par défaut (code / .env) relevées AVANT superposition */
    private static array $defaults = [];

    /** @return array<string, object{value: mixed, status: string, approved_at: ?string}> */
    public static function rows(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, 300, fn () => DB::table('app_settings')->get()->mapWithKeys(fn ($r) => [$r->key => (object) ['value' => json_decode($r->value, true), 'status' => $r->status, 'approved_at' => $r->approved_at]])->all());
        } catch (Throwable) {
            return [];
        }
    }

    public static function apply(): void
    {
        $rows = self::rows();
        foreach (array_keys(SettingDefinitions::all()) as $key) {
            self::$defaults[$key] ??= config('freeci.'.$key);
            if (isset($rows[$key])) {
                config(['freeci.'.$key => $rows[$key]->value]);
            } elseif (array_key_exists($key, self::$defaults)) {
                config(['freeci.'.$key => self::$defaults[$key]]);        // valeur supprimée : retour au défaut (processus longs, tests)
            }
        }
        // L'étiquette conservée dans l'accord suit le statut de la commission : « approuvée » seulement si le porteur l'a validée.
        self::$defaults['finance.commission_policy'] ??= config('freeci.finance.commission_policy');
        config(['freeci.finance.commission_policy' => ($rows['finance.commission_bp']->status ?? null) === 'approved' ? 'approuvee' : self::$defaults['finance.commission_policy']]);
    }

    public static function default(string $key): mixed
    {
        return self::$defaults[$key] ?? config('freeci.'.$key);
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
