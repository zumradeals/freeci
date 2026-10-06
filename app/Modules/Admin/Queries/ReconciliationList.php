<?php

namespace App\Modules\Admin\Queries;

use App\Shared\Dates;
use App\Shared\Money;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Paiements « à vérifier » ou « à traiter » : lecture seule des dossiers de rapprochement. Aucune opération financière n'est exécutée depuis cet écran. */
final class ReconciliationList
{
    public const REASONS = [
        'verification_mismatch' => 'Vérification incohérente (statut, montant, devise ou commande)', 'status_not_succeeded' => 'Succès annoncé mais non confirmé par le prestataire',
        'amount_mismatch' => 'Montant différent de l’accord', 'amount_missing' => 'Montant absent de la vérification', 'currency_mismatch' => 'Devise différente',
        'reference_mismatch' => 'Référence du prestataire non concordante', 'binding_missing' => 'Rattachement à la tentative non établi', 'environment_mismatch' => 'Environnement non concordant',
        'merchant_mismatch' => 'Compte marchand non concordant', 'gate_denied' => 'Commande ou compte non autorisé pour le paiement de démonstration', 'refunded_by_provider' => 'Remboursement signalé chez le prestataire',
        'pending_past_expiry' => 'Tentative restée ouverte après l’expiration du lien',
    ];

    public function counts(): int
    {
        return DB::table('reconciliation_cases')->whereNull('resolved_at')->count();
    }

    public function page(?string $status, int $perPage): LengthAwarePaginator
    {
        $q = DB::table('reconciliation_cases as r')->leftJoin('orders as o', 'o.id', '=', 'r.order_id')->leftJoin('payments as p', 'p.id', '=', 'r.payment_id')
            ->when($status === 'resolved', fn ($w) => $w->whereNotNull('r.resolved_at'), fn ($w) => $w->whereNull('r.resolved_at'))
            ->orderByDesc('r.created_at')->select('r.*', 'o.reference as order_ref', 'o.state as order_state', 'p.provider', 'p.environment', 'p.state as payment_state', 'p.amount_xof', 'p.provider_reference');

        return $q->paginate($perPage)->withQueryString()->through(function ($r) {
            $label = self::REASONS[$r->reason] ?? (str_starts_with($r->reason, 'paid_after_order_') ? 'Paiement reçu après la fin de la commande ('.substr($r->reason, 17).') : à traiter, rien n’est relancé' : (str_starts_with($r->reason, 'confirmed_after_') ? 'Succès tardif après un échec ou une expiration : à traiter, rien n’est relancé' : $r->reason));

            return [
                'id' => $r->id, 'reason' => $label, 'order' => $r->order_ref, 'orderState' => $r->order_state, 'provider' => $r->provider === 'genius_pay' ? 'Genius Pay' : 'Simulateur interne',
                'environment' => ['simulator' => 'Simulateur', 'sandbox' => 'Bac à sable', 'live' => 'Réel'][$r->environment] ?? $r->environment, 'paymentState' => $r->payment_state,
                'amount' => $r->amount_xof === null ? null : Money::xof((int) $r->amount_xof)->formatted().' FCFA', 'reference' => $r->provider_reference, 'when' => Dates::format(Carbon::parse($r->created_at)),
                'resolved' => $r->resolved_at !== null, 'note' => $r->resolution_note,
            ];
        });
    }
}
