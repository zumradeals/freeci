<?php

namespace App\Modules\Missions\Support;

use App\Modules\Catalog\Support\PrivateContact;

/** Règles du plan de jalons d'une proposition (F-13) : bornes provisoires configurables, somme égale au prix total, jamais de coordonnées privées. */
final class MilestoneRules
{
    /**
     * Lignes saisies → jalons normalisés. Une ligne entièrement vide est ignorée.
     *
     * @return list<array{title: string, scope: string, price_xof: ?int, days: ?int}>
     */
    public static function normalize(mixed $rows): array
    {
        $int = fn ($v) => trim((string) $v) === '' ? null : (int) preg_replace('/[\s\x{202F}\x{00A0}]/u', '', (string) $v);
        $out = [];
        foreach (is_array($rows) ? array_slice(array_values($rows), 0, 12) : [] as $r) {
            if (! is_array($r)) {
                continue;
            }
            $m = ['title' => trim((string) preg_replace('/\s+/u', ' ', (string) ($r['title'] ?? ''))), 'scope' => trim((string) ($r['scope'] ?? '')), 'price_xof' => $int($r['price'] ?? null), 'days' => $int($r['days'] ?? null)];
            if ($m['title'] !== '' || $m['scope'] !== '' || $m['price_xof'] !== null || $m['days'] !== null) {
                $out[] = $m;
            }
        }

        return $out;
    }

    /**
     * @param  list<array{title: string, scope: string, price_xof: ?int, days: ?int}>  $ms
     * @return array<string, string> erreurs par champ
     */
    public static function errors(array $ms, ?int $totalPrice): array
    {
        $c = config('freeci.missions.milestones');
        $e = [];
        [$nMin, $nMax] = $c['count'];
        if (count($ms) < $nMin || count($ms) > $nMax) {
            return ['milestones' => "Un plan compte de {$nMin} à {$nMax} jalons."];
        }
        $sum = 0;
        foreach ($ms as $i => $m) {
            $n = $i + 1;
            if (mb_strlen($m['title']) < $c['title'][0] || mb_strlen($m['title']) > $c['title'][1]) {
                $e["milestones.$i.title"] = "Jalon {$n} : un titre de {$c['title'][0]} à {$c['title'][1]} caractères.";
            }
            if (mb_strlen($m['scope']) < $c['scope'][0] || mb_strlen($m['scope']) > $c['scope'][1]) {
                $e["milestones.$i.scope"] = "Jalon {$n} : décrivez ce qui est livré ({$c['scope'][0]} à {$c['scope'][1]} caractères).";
            }
            if ($m['price_xof'] === null || $m['price_xof'] < $c['price_min']) {
                $e["milestones.$i.price"] = "Jalon {$n} : au moins ".number_format($c['price_min'], 0, ',', ' ').' FCFA.';
            }
            if ($m['days'] === null || $m['days'] < $c['days'][0] || $m['days'] > $c['days'][1]) {
                $e["milestones.$i.days"] = "Jalon {$n} : un délai de {$c['days'][0]} à {$c['days'][1]} jours.";
            }
            if (! isset($e["milestones.$i.scope"]) && PrivateContact::found($m['title']."\n".$m['scope'])) {
                $e["milestones.$i.scope"] = "Jalon {$n} : retirez les coordonnées privées (adresse e-mail, numéro de téléphone).";
            }
            $sum += (int) $m['price_xof'];
        }
        if ($e === [] && $totalPrice !== null && $sum !== $totalPrice) {
            $e['milestones'] = 'La somme des jalons ('.number_format($sum, 0, ',', ' ').' FCFA) doit être égale au prix total ('.number_format($totalPrice, 0, ',', ' ').' FCFA).';
        }

        return $e;
    }
}
