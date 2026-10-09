<?php

namespace App\Modules\Catalog\Support;

use Illuminate\Validation\ValidationException;

/**
 * Formules (2 ou 3 niveaux d'un même service) et options payantes (F-08). Les formules et les options font partie du contenu CONTRÔLÉ d'un service : elles sont rédigées dans une version,
 * relues par la modération, copiées dans le service à l'approbation, puis figées dans l'accord de chaque commande (formule choisie, options retenues, prix et délai de base).
 * Le prix du service (colonne `price_xof`) est celui de la formule la MOINS CHÈRE et son délai celui de la formule la plus RAPIDE : filtres et tris du catalogue restent valables.
 * Bornes : paramètres provisoires (config freeci.catalog). Aucun calcul n'est fait côté navigateur qui ne soit refait par le serveur.
 */
final class ServiceTiers
{
    /**
     * @param  array<string, mixed>  $in  saisie brute : pricing_mode (single|tiers), tiers[n][name|price_xof|delivery_days|revisions_included|includes], options[n][label|price_xof|delivery_days]
     * @return array{tiers: ?list<array<string, mixed>>, options: ?list<array<string, mixed>>}
     */
    public static function normalize(array $in): array
    {
        $int = fn ($v) => ($v === null || trim((string) $v) === '') ? null : (int) preg_replace('/[\s\x{202F}\x{00A0}]/u', '', (string) $v);
        $text = fn ($v) => trim((string) preg_replace('/\s+/u', ' ', (string) $v));
        $lines = fn ($v) => array_values(array_filter(array_map(fn ($l) => trim((string) preg_replace('/\s+/u', ' ', $l)), is_array($v) ? $v : (preg_split('/\R/u', (string) $v) ?: [])), fn ($l) => $l !== ''));

        $tiers = null;
        if (($in['pricing_mode'] ?? 'single') === 'tiers') {
            $tiers = [];
            foreach (array_values(is_array($in['tiers'] ?? null) ? $in['tiers'] : []) as $t) {
                $row = ['name' => $text($t['name'] ?? ''), 'price_xof' => $int($t['price_xof'] ?? null), 'delivery_days' => $int($t['delivery_days'] ?? null),
                    'revisions_included' => $int($t['revisions_included'] ?? null), 'includes' => $lines($t['includes'] ?? '')];
                if ($row['name'] !== '' || $row['price_xof'] !== null || $row['delivery_days'] !== null || $row['includes'] !== []) {
                    $tiers[] = $row + ['revisions_included' => $row['revisions_included'] ?? 0];
                }
            }
        }
        $options = [];
        foreach (array_values(is_array($in['options'] ?? null) ? $in['options'] : []) as $o) {
            $row = ['label' => $text($o['label'] ?? ''), 'price_xof' => $int($o['price_xof'] ?? null), 'delivery_days' => $int($o['delivery_days'] ?? null) ?? 0];
            if ($row['label'] !== '' || $row['price_xof'] !== null) {
                $options[] = $row;
            }
        }

        return ['tiers' => $tiers, 'options' => $options === [] ? null : $options];
    }

    /** Valeurs du service dérivées des formules : prix de la moins chère, délai de la plus rapide, corrections de la moins chère. @return array{price_xof: ?int, delivery_days: ?int, revisions_included: ?int} */
    public static function derive(array $tiers): array
    {
        $prices = array_values(array_filter(array_column($tiers, 'price_xof'), fn ($v) => $v !== null));
        $days = array_values(array_filter(array_column($tiers, 'delivery_days'), fn ($v) => $v !== null));
        $cheapest = collect($tiers)->filter(fn ($t) => $t['price_xof'] !== null)->sortBy('price_xof')->first();

        return ['price_xof' => $prices === [] ? null : min($prices), 'delivery_days' => $days === [] ? null : min($days), 'revisions_included' => $cheapest['revisions_included'] ?? null];
    }

    /**
     * @param  ?list<array<string, mixed>>  $tiers
     * @param  ?list<array<string, mixed>>  $options
     * @return array<string, string> erreurs par champ (vide si conforme)
     */
    public static function errors(?array $tiers, ?array $options, bool $strict): array
    {
        $c = config('freeci.catalog');
        $e = [];
        $len = fn (string $s) => mb_strlen($s);
        $money = fn (int $n) => number_format($n, 0, ',', ' ');

        if ($tiers !== null) {
            [$min, $max] = $c['tiers'];
            if (count($tiers) > $max) {
                $e['tiers'] = "Au plus {$max} formules.";
            } elseif ($strict && count($tiers) < $min) {
                $e['tiers'] = "Proposez au moins {$min} formules, ou repassez à une offre unique.";
            }
            $prev = null;
            $names = [];
            foreach ($tiers as $i => $t) {
                [$nMin, $nMax] = $c['tier_name'];
                if ($len($t['name']) < $nMin || $len($t['name']) > $nMax) {
                    $e["tiers.$i.name"] = "Le nom fait de {$nMin} à {$nMax} caractères.";
                } elseif (in_array(mb_strtolower($t['name']), $names, true)) {
                    $e["tiers.$i.name"] = 'Deux formules ne peuvent pas porter le même nom.';
                }
                $names[] = mb_strtolower($t['name']);
                [$pMin, $pMax] = $c['price_xof'];
                if ($t['price_xof'] === null || $t['price_xof'] < $pMin || $t['price_xof'] > $pMax) {
                    $e["tiers.$i.price_xof"] = 'Le prix va de '.$money($pMin).' à '.$money($pMax).' FCFA.';
                } elseif ($prev !== null && $t['price_xof'] <= $prev) {
                    $e["tiers.$i.price_xof"] = 'Les prix des formules doivent être strictement croissants.';
                }
                $prev = $t['price_xof'] ?? $prev;
                [$dMin, $dMax] = $c['delivery_days'];
                if ($t['delivery_days'] === null || $t['delivery_days'] < $dMin || $t['delivery_days'] > $dMax) {
                    $e["tiers.$i.delivery_days"] = "Le délai va de {$dMin} à {$dMax} jours.";
                }
                [$rMin, $rMax] = $c['revisions'];
                if ($t['revisions_included'] < $rMin || $t['revisions_included'] > $rMax) {
                    $e["tiers.$i.revisions_included"] = "Les corrections vont de {$rMin} à {$rMax}.";
                }
                if (count($t['includes']) > $c['tier_includes_max']) {
                    $e["tiers.$i.includes"] = 'Au plus '.$c['tier_includes_max'].' éléments par formule.';
                } elseif ($strict && $t['includes'] === []) {
                    $e["tiers.$i.includes"] = 'Indiquez ce que comprend cette formule (une ligne par élément).';
                } elseif (collect($t['includes'])->contains(fn ($l) => $len($l) > $c['line_max'])) {
                    $e["tiers.$i.includes"] = 'Chaque ligne fait au plus '.$c['line_max'].' caractères.';
                }
                foreach (['name' => $t['name'], 'includes' => implode("\n", $t['includes'])] as $f => $txt) {
                    if (! isset($e["tiers.$i.$f"]) && PrivateContact::found($txt)) {
                        $e["tiers.$i.$f"] = 'Retirez les coordonnées privées : les échanges passent par FreeCI.';
                    }
                }
            }
        }

        if ($options !== null) {
            if (count($options) > $c['options_max']) {
                $e['options'] = 'Au plus '.$c['options_max'].' options.';
            }
            $labels = [];
            foreach ($options as $i => $o) {
                [$lMin, $lMax] = $c['option_label'];
                if ($len($o['label']) < $lMin || $len($o['label']) > $lMax) {
                    $e["options.$i.label"] = "Le libellé fait de {$lMin} à {$lMax} caractères.";
                } elseif (in_array(mb_strtolower($o['label']), $labels, true)) {
                    $e["options.$i.label"] = 'Deux options ne peuvent pas porter le même libellé.';
                } elseif (PrivateContact::found($o['label'])) {
                    $e["options.$i.label"] = 'Retirez les coordonnées privées : les échanges passent par FreeCI.';
                }
                $labels[] = mb_strtolower($o['label']);
                [$pMin, $pMax] = $c['option_price'];
                if ($o['price_xof'] === null || $o['price_xof'] < $pMin || $o['price_xof'] > $pMax) {
                    $e["options.$i.price_xof"] = 'Le prix d’une option va de '.$money($pMin).' à '.$money($pMax).' FCFA.';
                }
                [$jMin, $jMax] = $c['option_days'];
                if ($o['delivery_days'] < $jMin || $o['delivery_days'] > $jMax) {
                    $e["options.$i.delivery_days"] = "Les jours en plus ou en moins vont de {$jMin} à +{$jMax}.";
                }
            }
            $extra = array_sum(array_map(fn ($o) => max(0, (int) $o['delivery_days']), $options));
            if ($extra > $c['options_days_total_max']) {
                $e['options'] = 'Le total des jours ajoutés par les options ne dépasse pas '.$c['options_days_total_max'].'.';
            }
        }

        // Plafond du total : la formule (ou l'offre unique) la plus chère plus TOUTES les options ne dépasse pas le plafond d'un service.
        $top = $tiers !== null ? (max(array_column($tiers, 'price_xof') ?: [0]) ?: 0) : 0;
        if ($tiers !== null && $options !== null && ! isset($e['options'])) {
            $total = $top + array_sum(array_map(fn ($o) => (int) $o['price_xof'], $options));
            if ($total > $c['price_xof'][1]) {
                $e['options'] = 'La formule la plus chère plus toutes les options atteint '.$money($total).' FCFA : le total ne dépasse pas '.$money($c['price_xof'][1]).' FCFA.';
            }
        }

        return $e;
    }

    /**
     * Sélection d'un client : formule (1, 2 ou 3) et options (numéros 1…), recalculée par le SERVEUR à partir des formules et options PUBLIÉES. Aucun montant n'est jamais lu dans la requête.
     *
     * @param  ?list<array<string, mixed>>  $tiers
     * @param  ?list<array<string, mixed>>  $options
     * @param  list<int|string>  $optionNos
     * @return array{tier: ?array<string, mixed>, tier_no: ?int, options: list<array<string, mixed>>, base_price: int, price: int, base_days: int, days: int, revisions: int}
     *
     * @throws ValidationException
     */
    public static function resolve(?array $tiers, ?array $options, int|string|null $tierNo, array $optionNos, int $basePrice, int $baseDays, int $baseRevisions): array
    {
        $tier = null;
        $no = null;
        if ($tiers !== null && $tiers !== []) {
            $no = is_numeric($tierNo) ? (int) $tierNo : 0;
            if ($no < 1 || $no > count($tiers)) {
                throw ValidationException::withMessages(['tier' => 'Choisissez une formule.']);
            }
            $tier = $tiers[$no - 1];
            [$basePrice, $baseDays, $baseRevisions] = [(int) $tier['price_xof'], (int) $tier['delivery_days'], (int) $tier['revisions_included']];
        }
        $picked = [];
        foreach (array_values(array_unique(array_map('strval', $optionNos))) as $n) {
            if (! ctype_digit($n) || (int) $n < 1 || (int) $n > count($options ?? [])) {
                throw ValidationException::withMessages(['options' => 'Une option choisie n’existe plus : actualisez la page.']);
            }
            $picked[(int) $n] = ['no' => (int) $n] + $options[(int) $n - 1];
        }
        ksort($picked);
        $picked = array_values($picked);

        return [
            'tier' => $tier, 'tier_no' => $no, 'options' => $picked, 'base_price' => $basePrice, 'price' => $basePrice + array_sum(array_map(fn ($o) => (int) $o['price_xof'], $picked)),
            'base_days' => $baseDays, 'days' => max(1, $baseDays + array_sum(array_map(fn ($o) => (int) $o['delivery_days'], $picked))), 'revisions' => $baseRevisions,
        ];
    }
}
