<?php

namespace App\Console\Commands;

use App\Modules\Finance\Actions\FinancialOperations;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rapprochement des opérations de remboursement envoyées par API : LECTURE seule du paiement chez le prestataire (jamais d'envoi, jamais de renvoi).
 * Un envoi resté « en cours » au-delà de 10 minutes passe « à vérifier » ; un statut « refunded » est signalé mais ne confirme RIEN (montant et rattachement non établis).
 * Aucune opération n'est confirmée, échouée ni relancée ici : seul un rapprochement manuel documenté conclut.
 */
class FinanceReconcile extends Command
{
    protected $signature = 'freeci:finance:reconcile {--limit=50 : nombre maximal d\'opérations par passe}';

    protected $description = 'Rapproche les remboursements Genius Pay en cours ou à vérifier (lecture seule chez le prestataire, aucun renvoi) ; confirme sur preuve complète du prestataire.';

    public function handle(FinancialOperations $ops): int
    {
        $ids = DB::table('financial_operations')->where('kind', 'refund')->where('execution_mode', 'api')->whereIn('state', ['in_progress', 'to_verify'])
            ->where(fn ($q) => $q->whereNull('last_checked_at')->orWhere('last_checked_at', '<', now()->subMinutes(5)))->orderBy('created_at')->limit(max(1, (int) $this->option('limit')))->pluck('id');
        $n = ['confirmed_on_provider_proof' => 0, 'provider_reports_refunded' => 0, 'pending' => 0, 'uncertain' => 0, 'not_applicable' => 0];
        foreach ($ids as $id) {
            try {
                $n[$ops->reconcile($id)]++;
            } catch (\Throwable $e) {
                report($e);
                $n['uncertain']++;
            }
        }
        $this->info("Opérations lues : {$ids->count()} · confirmées sur preuve du prestataire (remboursement total lu « remboursé », même transaction et même montant) : {$n['confirmed_on_provider_proof']} · « remboursé » sans preuve suffisante (à rapprocher manuellement) : {$n['provider_reports_refunded']} · autres : ".($n['pending'] + $n['uncertain']).'. Aucun renvoi n’est jamais émis.');

        return self::SUCCESS;
    }
}
