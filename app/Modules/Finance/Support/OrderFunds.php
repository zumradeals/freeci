<?php

namespace App\Modules\Finance\Support;

use Illuminate\Support\Facades\DB;

/** Lecture des fonds d'une commande À PARTIR DU REGISTRE (source unique) et des opérations. Aucune écriture. */
final class OrderFunds
{
    /** Solde « escrow » (fonds encaissés non encore alloués ni réservés) de la commande. */
    public static function escrow(string $orderId, bool $simulated): int
    {
        return (int) DB::table('ledger_lines')->join('ledger_batches', 'ledger_batches.id', '=', 'ledger_lines.batch_id')
            ->where('ledger_batches.order_id', $orderId)->where('ledger_lines.account', FinancialPolicy::account('escrow', $simulated))->sum('ledger_lines.amount_xof');
    }

    /** @return object|null le paiement confirmé de la commande */
    public static function confirmedPayment(string $orderId): ?object
    {
        return DB::table('payments')->where('order_id', $orderId)->where('state', 'confirmed')->first();
    }

    /**
     * Synthèse financière d'une commande (entiers FCFA). « Montant dû au freelance » n'est CALCULÉ ici que pour l'information : il n'est dû, réservé puis
     * reversé que par une opération de reversement.
     *
     * @return array<string, mixed>
     */
    public static function summary(string $orderId): array
    {
        $payment = self::confirmedPayment($orderId);
        $ops = DB::table('financial_operations')->where('order_id', $orderId)->get();
        $sum = fn (string $kind, array $states) => (int) $ops->where('kind', $kind)->whereIn('state', $states)->sum('amount_xof');
        $bp = DB::table('order_agreements')->where('order_id', $orderId)->value('commission_bp');
        $paid = $payment === null ? 0 : (int) $payment->amount_xof;
        $refunded = $sum('refund', ['confirmed']);
        $refundOpen = $sum('refund', ['requested', 'approved', 'in_progress', 'to_verify']);
        $payout = $ops->where('kind', 'payout')->whereIn('state', ['requested', 'approved', 'in_progress', 'to_verify', 'confirmed'])->first();
        // Base prospective : encaissé − remboursements confirmés ou réservés (conservateur) ; si un reversement existe, ses montants figés font foi.
        $base = $payout !== null ? (int) $payout->base_xof : max(0, $paid - $refunded - $refundOpen);
        $commission = $payout !== null ? (int) $payout->commission_xof : ($bp === null ? null : FinancialPolicy::commission($base, (int) $bp));
        $due = $payout !== null ? (int) $payout->amount_xof : ($commission === null ? null : $base - $commission);

        return [
            'simulated' => $payment === null ? null : (bool) $payment->is_simulated, 'paid' => $paid, 'provider_fee' => $payment?->provider_fee_xof === null ? null : (int) $payment->provider_fee_xof,
            'refunded' => $refunded, 'refund_open' => $refundOpen, 'commission_bp' => $bp === null ? null : (int) $bp, 'commission' => $commission, 'due' => $due, 'base' => $base,
            'payout_state' => $payout?->state, 'payout_reserved' => $payout !== null && in_array($payout->state, ['requested', 'approved', 'in_progress', 'to_verify'], true) ? (int) $payout->amount_xof : 0,
            'paid_out' => $sum('payout', ['confirmed']), 'escrow' => $payment === null ? 0 : self::escrow($orderId, (bool) $payment->is_simulated),
        ];
    }
}
