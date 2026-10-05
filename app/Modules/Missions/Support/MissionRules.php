<?php

namespace App\Modules\Missions\Support;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Support\PrivateContact;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/** Bornes et normalisation du contenu d'une mission (config freeci.missions : paramètres provisoires). */
final class MissionRules
{
    public const FIELDS = ['category_id', 'title', 'description', 'budget_xof', 'application_deadline', 'client_inputs', 'brief_requires_files'];

    /** @return array<string, mixed> */
    public static function normalize(array $in): array
    {
        $lines = fn ($v) => array_values(array_filter(array_map(fn ($l) => trim((string) preg_replace('/\s+/u', ' ', $l)), is_array($v) ? $v : (preg_split('/\R/u', (string) $v) ?: [])), fn ($l) => $l !== ''));
        $budget = trim((string) ($in['budget_xof'] ?? ''));
        $deadline = null;
        $raw = trim((string) ($in['application_deadline'] ?? ''));
        if ($raw !== '') {
            $d = Carbon::createFromFormat('!Y-m-d', $raw, 'Africa/Abidjan');
            $deadline = $d !== false && $d->format('Y-m-d') === $raw ? $d->setTime(23, 59, 0) : 'invalid';
        }

        return [
            'category_id' => trim((string) ($in['category_id'] ?? '')),
            'title' => trim((string) preg_replace('/\s+/u', ' ', (string) ($in['title'] ?? ''))),
            'description' => trim((string) ($in['description'] ?? '')),
            'budget_xof' => $budget === '' ? null : (int) preg_replace('/[\s\x{202F}\x{00A0}]/u', '', $budget),
            'application_deadline' => $deadline,
            'client_inputs' => $lines($in['client_inputs'] ?? ''),
            'brief_requires_files' => ! empty($in['brief_requires_files']),
        ];
    }

    public static function validate(array $v, bool $strict): void
    {
        $c = config('freeci.missions');
        $e = [];
        if ($v['category_id'] === '' ? $strict : ! Category::query()->whereKey($v['category_id'])->exists()) {
            $e['category_id'] = 'Choisissez une catégorie.';
        }
        [$tMin, $tMax] = $c['title'];
        $len = mb_strlen($v['title']);
        if ($len > $tMax || ($len > 0 || $strict) && $len < ($strict ? $tMin : 1)) {
            $e['title'] = "Le titre doit faire entre {$tMin} et {$tMax} caractères.";
        }
        [$dMin, $dMax] = $c['description'];
        $len = mb_strlen($v['description']);
        if ($len > $dMax || ($strict && $len < $dMin)) {
            $e['description'] = "La description doit faire entre {$dMin} et {$dMax} caractères : décrivez le besoin, les formats attendus, les limites.";
        }
        [$bMin, $bMax] = $c['budget_xof'];
        if ($v['budget_xof'] === null ? $strict : ($v['budget_xof'] < ($strict ? $bMin : 1) || $v['budget_xof'] > $bMax)) {
            $e['budget_xof'] = 'Le budget doit être compris entre '.number_format($bMin, 0, ',', ' ').' et '.number_format($bMax, 0, ',', ' ').' FCFA (valeurs provisoires).';
        }
        $dl = $v['application_deadline'];
        if ($dl === 'invalid') {
            $e['application_deadline'] = 'Indiquez une date valide.';
        } elseif ($dl === null) {
            if ($strict) {
                $e['application_deadline'] = 'Indiquez la date limite de candidature.';
            }
        } elseif ($strict && ($dl->lte(now()->addDay()->startOfDay()) || $dl->gt(now()->addDays($c['deadline_max_days'])))) {
            $e['application_deadline'] = 'La date limite doit être comprise entre demain et dans '.$c['deadline_max_days'].' jours.';
        }
        if (count($v['client_inputs']) > $c['client_inputs_max']) {
            $e['client_inputs'] = "Au plus {$c['client_inputs_max']} éléments.";
        } elseif (collect($v['client_inputs'])->contains(fn ($l) => mb_strlen($l) > $c['line_max'])) {
            $e['client_inputs'] = 'Chaque ligne fait au plus '.$c['line_max'].' caractères.';
        }
        foreach (['title', 'description', 'client_inputs'] as $f) {
            if (! isset($e[$f]) && PrivateContact::found(is_array($v[$f]) ? implode("\n", $v[$f]) : $v[$f])) {
                $e[$f] = 'Retirez les coordonnées privées (adresse e-mail, numéro de téléphone) : une mission est publique, les échanges passent par FreeCI.';
            }
        }
        if ($e !== []) {
            throw ValidationException::withMessages($e);
        }
    }
}
