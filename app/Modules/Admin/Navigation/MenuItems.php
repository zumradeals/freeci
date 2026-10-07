<?php

namespace App\Modules\Admin\Navigation;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Menus du site (en-tête et trois colonnes du pied de page). Tant qu'une zone n'a pas été personnalisée dans l'administration, les liens d'origine
 * s'appliquent ; une zone personnalisée remplace entièrement l'origine. Le cache ne contient que des tableaux (jamais d'objets).
 */
final class MenuItems
{
    public const CACHE_KEY = 'menu_items.v1';

    /** zone => titre affiché dans l'administration */
    public const AREAS = ['header' => 'Menu principal (en-tête)', 'footer_discover' => 'Pied de page : Découvrir', 'footer_help' => 'Pied de page : Aide', 'footer_info' => 'Pied de page : Informations'];

    public const MAX = ['header' => 7, 'footer_discover' => 8, 'footer_help' => 8, 'footer_info' => 8];

    /** @return list<array{label: string, destination: string, guest?: bool}> */
    public static function defaults(string $area): array
    {
        return match ($area) {
            'header' => [
                ['label' => 'Services', 'destination' => 'services'], ['label' => 'Missions', 'destination' => 'missions'], ['label' => 'Freelances', 'destination' => 'freelances'],
                ['label' => 'Comment ça marche', 'destination' => 'how'], ['label' => 'Devenir freelance', 'destination' => 'freelance_activate', 'guest' => true],
            ],
            'footer_discover' => [['label' => 'Services', 'destination' => 'services'], ['label' => 'Missions', 'destination' => 'missions'], ['label' => 'Freelances', 'destination' => 'freelances']],
            'footer_help' => [['label' => 'Comment ça marche', 'destination' => 'how'], ['label' => 'Centre d’aide', 'destination' => 'help'], ['label' => 'Contact', 'destination' => 'contact']],
            'footer_info' => [['label' => 'Conditions d’utilisation', 'destination' => 'terms'], ['label' => 'Confidentialité', 'destination' => 'privacy'], ['label' => 'Mentions légales', 'destination' => 'legal']],
            default => [],
        };
    }

    /** @return array<string, list<array<string, mixed>>> lignes personnalisées par zone (tableaux simples) */
    private static function custom(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, fn () => DB::table('menu_items')->orderBy('area')->orderBy('position')->orderBy('id')->get()
                ->groupBy('area')->map(fn ($rows) => $rows->map(fn ($r) => ['id' => (int) $r->id, 'label' => $r->label, 'destination' => $r->destination, 'visible' => (bool) $r->visible])->values()->all())->all());
        } catch (Throwable) {
            return [];      // table absente (avant migration) : liens d'origine
        }
    }

    public static function isCustomized(string $area): bool
    {
        return isset(self::custom()[$area]);
    }

    /** Liens à afficher : [libellé, adresse] ; les liens sans destination valide ou masqués sont écartés. @return list<array{0: string, 1: string, 2: string}> */
    public static function links(string $area): array
    {
        $out = [];
        $custom = self::custom()[$area] ?? null;
        $rows = $custom ?? self::defaults($area);
        foreach ($rows as $r) {
            if (($custom !== null && ! ($r['visible'] ?? true)) || ($custom === null && ($r['guest'] ?? false) && auth()->check())) {
                continue;
            }
            $url = Destinations::url($r['destination'] ?? null);
            if ($url !== null) {
                $out[] = [$r['label'], $url, (string) $r['destination']];
            }
        }

        return $out;
    }

    /** Lignes éditables d'une zone (personnalisées, ou origine présentée sans identifiant). @return list<array<string, mixed>> */
    public static function editable(string $area): array
    {
        return self::custom()[$area] ?? array_map(fn ($d) => ['id' => null, 'label' => $d['label'], 'destination' => $d['destination'], 'visible' => true], self::defaults($area));
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
