<?php

namespace App\Modules\Admin\Settings;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Applique les valeurs saisies en administration par-dessus la configuration du code / du .env. Appelé au démarrage de chaque requête :
 * une seule lecture (mise en cache, invalidée à chaque écriture). Table absente (avant migration) ou base indisponible : on ignore, rien ne casse.
 */
final class AppSettings
{
    private const CACHE_KEY = 'app_settings.v2';

    /** @var array<string, mixed> valeurs par défaut (code / .env) relevées AVANT superposition */
    private static array $defaults = [];

    /**
     * Valeurs saisies, indexées par clé. Le cache ne reçoit que des TABLEAUX : avec le cache « database » (production), des objets mis en cache
     * reviennent « incomplets » (cache.serializable_classes = false) et provoquent une erreur 500.
     *
     * @return array<string, object{value: mixed, status: string, approved_at: ?string}>
     */
    public static function rows(): array
    {
        try {
            $plain = Cache::remember(self::CACHE_KEY, 300, fn () => DB::table('app_settings')->get()->mapWithKeys(fn ($r) => [$r->key => ['value' => json_decode($r->value, true), 'status' => $r->status, 'approved_at' => $r->approved_at]])->all());
        } catch (Throwable) {
            return [];
        }

        return array_map(fn ($r) => (object) $r, is_array($plain) ? $plain : []);
    }

    public static function apply(): void
    {
        $rows = self::rows();
        foreach (SettingDefinitions::all() as $key => $def) {
            $path = $def['path'];
            self::$defaults[$key] ??= config($path);
            if (isset($rows[$key])) {
                $value = $rows[$key]->value;
                if ($def['secret']) {
                    try {
                        $value = Crypt::decryptString((string) $value);
                    } catch (Throwable) {
                        $value = self::$defaults[$key];       // clé de chiffrement changée : on retombe sur la valeur du serveur
                    }
                }
                config([$path => $value]);
            } else {
                config([$path => self::$defaults[$key]]);        // valeur supprimée : retour au défaut (processus longs, tests)
            }
        }
        // L'étiquette conservée dans l'accord suit le statut de la commission : « approuvée » seulement si le porteur l'a validée.
        self::$defaults['finance.commission_policy'] ??= config('freeci.finance.commission_policy');
        config(['freeci.finance.commission_policy' => ($rows['finance.commission_bp']->status ?? null) === 'approved' ? 'approuvee' : self::$defaults['finance.commission_policy']]);
        // Un courrier déjà instancié dans ce processus reprend la nouvelle configuration.
        if (app()->resolved('mail.manager') && method_exists(app('mail.manager'), 'forgetMailers')) {
            app('mail.manager')->forgetMailers();
        }
    }

    public static function default(string $key): mixed
    {
        return self::$defaults[$key] ?? config(SettingDefinitions::all()[$key]['path'] ?? 'freeci.'.$key);
    }

    /** Texte administrable : la valeur saisie, ou à défaut le texte de départ (un champ vidé ne laisse jamais un courriel ou une page sans texte). */
    public static function text(string $key): string
    {
        return (string) (config(SettingDefinitions::all()[$key]['path'] ?? 'freeci.'.$key) ?: self::default($key));
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
