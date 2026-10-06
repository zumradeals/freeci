<?php

namespace App\Console\Commands;

use App\Modules\Finance\Actions\FinancialOperations;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rapprochement des opérations de remboursement envoyées par API : LECTURE seule du paiement chez le prestataire (jamais d'envoi, jamais de renvoi).
 * « refunded » ⇒ confirmé ; un envoi resté « en cours » au-delà de 10 minutes passe « à vérifier ». Aucune opération n'est échouée ni relancée ici :
 * constater un échec ou reprendre un envoi sont des actions humaines explicites.
 */
class FinanceReconcile extends Command
{
    protected $signature = 'freeci:finance:reconcile {--limit=50 : nombre maximal d\'opérations par passe}';

    protected $description = 'Rapproche les remboursements Genius Pay en cours ou à vérifier (lecture seule chez le prestataire, aucun renvoi).';

    public function handle(FinancialOperations $ops): int
    {
        $ids = DB::table('financial_operations')->where('kind', 'refund')->where('execution_mode', 'api')->whereIn('state', ['in_progress', 'to_verify'])
            ->where(fn ($q) => $q->whereNull('last_checked_at')->orWhere('last_checked_at', '<', now()->subMinutes(5)))->orderBy('created_at')->limit(max(1, (int) $this->option('limit')))->pluck('id');
        $n = ['confirmed' => 0, 'pending' => 0, 'uncertain' => 0, 'not_applicable' => 0];
        foreach ($ids as $id) {
            try {
                $n[$ops->reconcile($id)]++;
            } catch (\Throwable $e) {
                report($e);
                $n['uncertain']++;
            }
        }
        $this->info("Opérations lues : {$ids->count()} · confirmées : {$n['confirmed']} · toujours ouvertes : ".($n['pending'] + $n['uncertain']).'.');

        return self::SUCCESS;
    }
}
