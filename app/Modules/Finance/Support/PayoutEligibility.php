<?php

namespace App\Modules\Finance\Support;

use App\Modules\Support\Support\PayoutHolds;
use Illuminate\Support\Facades\DB;

/**
 * Éligibilité d'une commande au reversement : CALCULÉE (jamais stockée comme un versement). Elle ne dit pas qu'un reversement a eu lieu ;
 * elle dit si une opération de reversement PEUT être demandée. Le silence du client ne la rend jamais vraie : seule une validation explicite
 * (clôture « validated ») ou une décision motivée du support (« reversement à autoriser » / partielle) l'ouvre.
 */
final class PayoutEligibility
{
    public const REASONS = [
        'no_payment' => 'Aucun paiement Genius Pay confirmé.',
        'legacy_payment' => 'Paiement d’un ancien simulateur : non éligible.',
        'terms_missing' => 'Conditions financières non figées dans l’accord (commande antérieure au lot 11) : le taux de commission est inconnu.',
        'not_validated' => 'Travail non validé : ni validation explicite du client, ni décision motivée du support.',
        'dispute_open' => 'Litige ou demande d’annulation en cours.',
        'hold' => 'Blocage interne actif (dossier d’assistance).',
        'reconciliation_open' => 'Un dossier de rapprochement du paiement est ouvert : les fonds ne sont pas rapprochés.',
        'beneficiary_missing' => 'Aucun bénéficiaire vérifié et actif pour le freelance.',
        'payout_exists' => 'Un reversement est déjà demandé, en cours ou confirmé pour cette commande.',
        'refund_open' => 'Un remboursement est demandé ou en cours : ses fonds sont réservés.',
        'no_funds' => 'Aucun fonds disponible sur cette commande.',
        'amount_zero' => 'La part du freelance serait nulle.',
    ];

    /**
     * @return array{eligible: bool, reasons: list<string>, base: int, commission: ?int, due: ?int, bp: ?int, payment_id: ?string, beneficiary_id: ?string, simulated: ?bool}
     */
    public static function evaluate(object $order): array
    {
        $reasons = [];
        $payment = OrderFunds::confirmedPayment($order->id);
        $bp = DB::table('order_agreements')->where('order_id', $order->id)->value('commission_bp');
        $simulated = null;
        $base = 0;
        if ($payment === null) {
            $reasons[] = 'no_payment';
        } elseif ($payment->provider !== 'genius_pay') {
            $reasons[] = 'legacy_payment';
        } else {
            $simulated = (bool) $payment->is_simulated;
            $base = OrderFunds::escrow($order->id, $simulated);
        }
        if ($bp === null) {
            $reasons[] = 'terms_missing';
        }
        $validated = $order->state === 'validated' || $order->closure_reason === 'validated';
        $decided = DB::table('support_decisions as d')->join('support_cases as c', 'c.id', '=', 'd.case_id')->where('c.order_id', $order->id)->whereIn('d.financial_need', ['release', 'partial'])->exists();
        if (! $validated && ! $decided) {
            $reasons[] = 'not_validated';
        }
        if ($order->state === 'disputed') {
            $reasons[] = 'dispute_open';
        }
        if (PayoutHolds::isHeld($order->id)) {
            $reasons[] = 'hold';
        }
        if ($payment !== null && DB::table('reconciliation_cases')->where('order_id', $order->id)->whereNull('resolved_at')->exists()) {
            $reasons[] = 'reconciliation_open';
        }
        $beneficiary = DB::table('payout_beneficiaries')->where('user_id', $order->freelancer_id)->where('status', 'verified')->value('id');
        if ($beneficiary === null) {
            $reasons[] = 'beneficiary_missing';
        }
        if (DB::table('financial_operations')->where('order_id', $order->id)->where('kind', 'payout')->whereIn('state', ['requested', 'approved', 'in_progress', 'to_verify', 'confirmed'])->exists()) {
            $reasons[] = 'payout_exists';
        }
        if ($payment !== null && DB::table('financial_operations')->where('payment_id', $payment->id)->where('kind', 'refund')->whereIn('state', ['requested', 'approved', 'in_progress', 'to_verify'])->exists()) {
            $reasons[] = 'refund_open';
        }
        $commission = $bp === null ? null : FinancialPolicy::commission($base, (int) $bp);
        $due = $commission === null ? null : $base - $commission;
        if ($payment !== null && $payment->provider === 'genius_pay' && $base <= 0) {
            $reasons[] = 'no_funds';
        } elseif ($due !== null && $due <= 0 && $base > 0) {
            $reasons[] = 'amount_zero';
        }

        return ['eligible' => $reasons === [], 'reasons' => array_values(array_unique($reasons)), 'base' => $base, 'commission' => $commission, 'due' => $due, 'bp' => $bp === null ? null : (int) $bp,
            'payment_id' => $payment?->id, 'beneficiary_id' => $beneficiary, 'simulated' => $simulated];
    }
}
