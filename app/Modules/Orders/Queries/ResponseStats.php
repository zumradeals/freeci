<?php

namespace App\Modules\Orders\Queries;

use Illuminate\Support\Facades\DB;

/**
 * Réactivité d'un freelance, CALCULÉE à partir de ses demandes de prestation (jamais déclarée, jamais saisie) : ses 20 dernières demandes reçues dans les 90 derniers jours
 * qui ont reçu une réponse (acceptation ou refus) ou sont restées sans réponse après l'échéance. Une demande retirée par le client avant la réponse n'est pas comptée, ni une demande
 * encore dans son délai, ni une commande issue d'une mission (aucune demande à accepter). Minimum 5 demandes pour afficher quoi que ce soit.
 * Temps : médiane (une demande sans réponse compte comme la plus longue) ; taux : part des demandes traitées dans le délai.
 */
final class ResponseStats
{
    public const SAMPLE = 20;

    public const DAYS = 90;

    public const MIN = 5;

    /** @return array{count: int, label: ?string, rate: ?int, median_minutes: ?int} */
    public static function summarize(array $rows): array
    {
        $n = count($rows);
        if ($n < self::MIN) {
            return ['count' => $n, 'label' => null, 'rate' => null, 'median_minutes' => null];
        }
        $times = [];
        $inTime = 0;
        foreach ($rows as $r) {
            $answered = $r['answered_at'] !== null;
            $times[] = $answered ? max(0, strtotime($r['answered_at']) - strtotime($r['requested_at'])) : PHP_INT_MAX;
            if ($answered && strtotime($r['answered_at']) <= strtotime($r['deadline_at'])) {
                $inTime++;
            }
        }
        sort($times);
        $median = $times[intdiv($n - 1, 2)];
        $minutes = $median === PHP_INT_MAX ? null : (int) round($median / 60);

        return ['count' => $n, 'label' => self::bucket($minutes), 'rate' => (int) round(100 * $inTime / $n), 'median_minutes' => $minutes];
    }

    /** Tranche affichée aux clients : jamais la valeur brute. */
    public static function bucket(?int $minutes): ?string
    {
        return match (true) {
            $minutes === null => null,
            $minutes < 60 => 'moins d’1 h',
            $minutes < 360 => 'moins de 6 h',
            $minutes < 1440 => 'moins de 24 h',
            $minutes <= 2880 => 'moins de 48 h',
            default => null,
        };
    }

    /**
     * @param  list<string>  $userIds
     * @return array<string, array{count: int, label: ?string, rate: ?int, median_minutes: ?int}>
     */
    public function forFreelancers(array $userIds): array
    {
        $userIds = array_values(array_unique($userIds));
        if ($userIds === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($userIds), '?'));
        $rows = DB::select(<<<SQL
            SELECT freelancer_id, requested_at, answered_at, deadline_at FROM (
                SELECT o.freelancer_id, o.requested_at, o.response_deadline_at AS deadline_at,
                       CASE WHEN o.accepted_at IS NOT NULL THEN o.accepted_at WHEN o.closure_reason = 'declined' THEN o.closed_at ELSE NULL END AS answered_at,
                       row_number() OVER (PARTITION BY o.freelancer_id ORDER BY o.requested_at DESC, o.id) AS rn
                FROM orders o
                WHERE o.freelancer_id IN ({$in}) AND o.mission_id IS NULL AND o.requested_at >= now() - interval '90 days'
                  AND (o.accepted_at IS NOT NULL OR o.closure_reason IN ('declined', 'expired_acceptance') OR (o.state = 'awaiting_acceptance' AND o.response_deadline_at <= now()))
            ) t WHERE rn <= 20 ORDER BY freelancer_id, requested_at DESC
        SQL, $userIds);

        $by = [];
        foreach ($rows as $r) {
            $by[$r->freelancer_id][] = ['requested_at' => $r->requested_at, 'answered_at' => $r->answered_at, 'deadline_at' => $r->deadline_at];
        }
        $out = [];
        foreach ($userIds as $id) {
            $out[$id] = self::summarize($by[$id] ?? []);
        }

        return $out;
    }
}
