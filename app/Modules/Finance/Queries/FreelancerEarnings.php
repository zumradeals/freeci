<?php

namespace App\Modules\Finance\Queries;

use App\Modules\Accounts\Models\User;
use App\Modules\Finance\Support\FinanceLabels;
use App\Modules\Finance\Support\OrderFunds;
use App\Modules\Finance\Support\PayoutEligibility;
use App\Shared\Dates;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Revenus d'un FREELANCE : à venir (prestation non validée), bloqués, disponibles (éligibles, reversement pas encore demandé), en cours, reversés.
 * Réel et test sont des totaux SÉPARÉS : une commande de test n'alimente jamais un revenu réel. Un « montant disponible » n'est PAS un reversement :
 * l'exécution du reversement n'est pas disponible via Genius Pay (aucune API documentée) et reste une action manuelle du personnel.
 */
final class FreelancerEarnings
{
    private const BLOCKING = ['dispute_open', 'hold', 'reconciliation_open', 'refund_open'];

    /** @return array<string, mixed> */
    public function overview(User $freelancer, int $page = 1): array
    {
        $orders = DB::table('orders as o')->join('order_agreements as a', 'a.order_id', '=', 'o.id')->where('o.freelancer_id', $freelancer->getKey())
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('payments as p')->whereRaw('p.order_id = o.id')->where('p.state', 'confirmed'))
            ->select(['o.*', 'a.service_title'])->orderByDesc('o.updated_at')->orderByDesc('o.id')->lazy(100);

        $zero = fn () => ['upcoming' => 0, 'blocked' => 0, 'available' => 0, 'processing' => 0, 'paid' => 0];
        $totals = ['real' => $zero(), 'test' => $zero()];
        $rows = [];
        $page = max(1, $page);
        $perPage = 20;
        $count = 0;
        foreach ($orders as $o) {
            $e = PayoutEligibility::evaluate($o);
            $f = OrderFunds::summary($o->id);
            $bucket = ($f['simulated'] ?? true) ? 'test' : 'real';
            if ($e['reasons'] === ['legacy_payment'] || in_array('legacy_payment', $e['reasons'], true)) {
                continue;                                                      // ancien simulateur : jamais compté
            }
            if ($f['payout_state'] === null && $f['base'] === 0) {
                continue;                                                      // intégralement remboursée : rien à percevoir
            }
            $due = $f['due'];
            if ($f['payout_state'] === 'confirmed') {
                $cat = 'paid';
            } elseif ($f['payout_state'] !== null) {
                $cat = 'processing';
            } elseif ($due === null) {
                $cat = 'unknown';
            } elseif (array_intersect($e['reasons'], self::BLOCKING)) {
                $cat = 'blocked';
            } elseif ($e['eligible'] || $e['reasons'] === ['beneficiary_missing']) {
                $cat = 'available';
            } elseif (in_array('not_validated', $e['reasons'], true)) {
                $cat = 'upcoming';
            } else {
                $cat = 'blocked';
            }
            $amount = $f['payout_state'] !== null ? $f['due'] : $due;
            if ($cat !== 'unknown' && $amount !== null) {
                $totals[$bucket][$cat] += (int) $amount;
            }
            // Totals cover the full history; keep only this page's detail in memory.
            $index = $count++;
            if ($index < ($page - 1) * $perPage || $index >= $page * $perPage) {
                continue;
            }
            $rows[] = [
                'reference' => $o->reference, 'title' => $o->service_title, 'bucket' => $bucket, 'cat' => $cat, 'paid' => $f['paid'], 'refunded' => $f['refunded'], 'commission' => $f['commission'], 'bp' => $f['commission_bp'],
                'due' => $amount, 'state' => $f['payout_state'], 'state_label' => $f['payout_state'] === null ? null : FinanceLabels::PARTY_STATES[$f['payout_state']],
                'reasons' => array_map(fn ($r) => PayoutEligibility::REASONS[$r], $e['reasons']),
                'when' => Dates::format(Carbon::parse($o->updated_at)),
            ];
        }
        $b = DB::table('payout_beneficiaries')->where('user_id', $freelancer->getKey())->whereIn('status', ['pending', 'verified'])->first(['method', 'holder_name', 'status']);

        return ['totals' => $totals, 'rows' => $rows, 'pagination' => new LengthAwarePaginator($rows, $count, $perPage, $page, ['path' => LengthAwarePaginator::resolveCurrentPath()]), 'beneficiary' => $b === null ? null : ['method' => $b->method, 'holder' => $b->holder_name, 'status' => $b->status]];
    }
}
