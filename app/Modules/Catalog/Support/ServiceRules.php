<?php

namespace App\Modules\Catalog\Support;

use App\Modules\Catalog\Models\Category;
use Illuminate\Validation\ValidationException;

/**
 * Bornes et normalisation du contenu d'un service. Les bornes sont des PARAMÈTRES PROVISOIRES (config freeci.catalog).
 * Brouillon : on accepte un contenu incomplet mais on refuse tout ce qui est mal formé ou excessif. Soumission : tout doit être renseigné.
 */
final class ServiceRules
{
    public const FIELDS = ['category_id', 'title', 'summary', 'scope', 'price_xof', 'delivery_days', 'revisions_included', 'deliverables', 'exclusions', 'client_inputs', 'delivery_requires_files', 'brief_requires_files'];

    /** Normalise une saisie brute (formulaire) vers les colonnes d'une version. @return array<string, mixed> */
    public static function normalize(array $in): array
    {
        $lines = fn ($v) => array_values(array_filter(array_map(fn ($l) => trim((string) preg_replace('/\s+/u', ' ', $l)), is_array($v) ? $v : (preg_split('/\R/u', (string) $v) ?: [])), fn ($l) => $l !== ''));
        $int = fn ($v) => ($v === null || trim((string) $v) === '') ? null : (int) preg_replace('/[\s\x{202F}\x{00A0}]/u', '', (string) $v);

        return [
            'category_id' => trim((string) ($in['category_id'] ?? '')),
            'title' => trim((string) preg_replace('/\s+/u', ' ', (string) ($in['title'] ?? ''))),
            'summary' => trim((string) ($in['summary'] ?? '')),
            'scope' => trim((string) ($in['scope'] ?? '')),
            'price_xof' => $int($in['price_xof'] ?? null),
            'delivery_days' => $int($in['delivery_days'] ?? null),
            'revisions_included' => $int($in['revisions_included'] ?? null) ?? 0,
            'deliverables' => $lines($in['deliverables'] ?? ''),
            'exclusions' => $lines($in['exclusions'] ?? ''),
            'client_inputs' => $lines($in['client_inputs'] ?? ''),
            'delivery_requires_files' => ($in['delivery_mode'] ?? 'files') !== 'message',
            'brief_requires_files' => ! empty($in['brief_requires_files']),
        ];
    }

    /** @param array<string, mixed> $v colonnes normalisées */
    public static function validate(array $v, bool $strict): void
    {
        $c = config('freeci.catalog');
        $e = [];
        $len = fn (string $s) => mb_strlen($s);

        if ($v['category_id'] === '' ? $strict : ! Category::query()->whereKey($v['category_id'])->exists()) {
            $e['category_id'] = 'Choisissez une catégorie.';
        }
        [$tMin, $tMax] = $c['title'];
        if ($len($v['title']) > $tMax || ($strict || $v['title'] !== '') && $len($v['title']) < ($strict ? $tMin : 1)) {
            $e['title'] = "Le titre doit faire entre {$tMin} et {$tMax} caractères.";
        }
        [$sMin, $sMax] = $c['summary'];
        if ($len($v['summary']) > $sMax || ($strict && $len($v['summary']) < $sMin)) {
            $e['summary'] = "Le résumé doit faire entre {$sMin} et {$sMax} caractères (affiché sur les cartes du catalogue).";
        }
        [$dMin, $dMax] = $c['scope'];
        if ($len($v['scope']) > $dMax || ($strict && $len($v['scope']) < $dMin)) {
            $e['scope'] = "La description du périmètre doit faire entre {$dMin} et {$dMax} caractères : précisez ce qui est inclus.";
        }
        [$pMin, $pMax] = $c['price_xof'];
        if ($v['price_xof'] === null ? $strict : ($v['price_xof'] < ($strict ? $pMin : 1) || $v['price_xof'] > $pMax)) {
            $e['price_xof'] = 'Le prix doit être compris entre '.number_format($pMin, 0, ',', ' ').' et '.number_format($pMax, 0, ',', ' ').' FCFA.';
        }
        [$jMin, $jMax] = $c['delivery_days'];
        if ($v['delivery_days'] === null ? $strict : ($v['delivery_days'] < $jMin || $v['delivery_days'] > $jMax)) {
            $e['delivery_days'] = "Le délai doit être compris entre {$jMin} et {$jMax} jours.";
        }
        [$rMin, $rMax] = $c['revisions'];
        if ($v['revisions_included'] < $rMin || $v['revisions_included'] > $rMax) {
            $e['revisions_included'] = "Les corrections incluses vont de {$rMin} à {$rMax}.";
        }
        foreach (['deliverables' => ['livrable', $c['deliverables_max'], 1], 'exclusions' => ['exclusion', $c['exclusions_max'], 0], 'client_inputs' => ['élément à fournir', $c['client_inputs_max'], 0]] as $field => [$noun, $max, $min]) {
            if (count($v[$field]) > $max) {
                $e[$field] = "Au plus {$max} lignes.";
            } elseif ($strict && count($v[$field]) < $min) {
                $e[$field] = 'Indiquez au moins un '.$noun.' (une ligne par élément).';
            } elseif (collect($v[$field])->contains(fn ($l) => $len($l) > $c['line_max'])) {
                $e[$field] = 'Chaque ligne fait au plus '.$c['line_max'].' caractères.';
            }
        }
        foreach (['title', 'summary', 'scope', 'deliverables', 'exclusions', 'client_inputs'] as $field) {
            if (! isset($e[$field]) && PrivateContact::found(is_array($v[$field]) ? implode("\n", $v[$field]) : $v[$field])) {
                $e[$field] = 'Retirez les coordonnées privées (adresse e-mail, numéro de téléphone) : les échanges passent par FreeCI.';
            }
        }
        if ($e !== []) {
            throw ValidationException::withMessages($e);
        }
    }
}
