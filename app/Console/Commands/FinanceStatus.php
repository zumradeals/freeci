<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** État des conditions financières et des opérations. Aucun secret, aucune écriture. */
class FinanceStatus extends Command
{
    protected $signature = 'freeci:finance:status';

    protected $description = 'Conditions financières (propositions non validées), capacités Genius Pay et opérations par état.';

    public function handle(): int
    {
        $threshold = config('freeci.finance.dual_approval_threshold_xof');
        $this->table(['Paramètre', 'Valeur', 'Statut'], [
            ['Taux de commission des NOUVEAUX accords (FREECI_COMMISSION_BP)', config('freeci.finance.commission_bp').' pb ('.(config('freeci.finance.commission_bp') / 100).' %)', 'PROPOSITION non validée ; figé dans chaque accord à sa création'],
            ['Étiquette de politique', (string) config('freeci.finance.commission_policy'), '—'],
            ['Seuil de double validation (FREECI_FINANCE_DUAL_APPROVAL_XOF)', $threshold === null ? 'désactivée' : number_format((int) $threshold, 0, ',', ' ').' FCFA', 'NON VALIDÉ par le porteur'],
            ['Remboursement total par API Genius Pay', 'disponible', 'documenté : POST /payments/{reference}/refund'],
            ['Remboursement partiel par API', 'non exécuté', 'règles des remboursements successifs et idempotence non établies : enregistrement manuel'],
            ['Reversement par API Genius Pay', 'INDISPONIBLE', 'aucune API de reversement documentée : suivi interne + enregistrement manuel'],
        ]);
        $rows = DB::table('financial_operations')->selectRaw('kind, state, is_simulated, count(*) c, sum(amount_xof) s')->groupBy('kind', 'state', 'is_simulated')->orderBy('kind')->orderBy('state')->get();
        $this->table(['Type', 'État', 'Environnement', 'Nombre', 'Montant (FCFA)'], $rows->map(fn ($r) => [$r->kind, $r->state, $r->is_simulated ? 'test' : 'réel', $r->c, (int) $r->s])->all());
        $this->line('Accords sans taux figé (antérieurs au lot 11) : '.DB::table('order_agreements')->whereNull('commission_bp')->count().' · opérations « à vérifier » : '.DB::table('financial_operations')->where('state', 'to_verify')->count().'.');

        return self::SUCCESS;
    }
}
