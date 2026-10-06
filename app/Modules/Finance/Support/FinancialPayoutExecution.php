<?php

namespace App\Modules\Finance\Support;

use App\Modules\Support\Contracts\PayoutExecution;
use Illuminate\Support\Facades\DB;

/**
 * Le reversement est-il EXÉCUTÉ ? Oui seulement s'il est confirmé, ou si un envoi est en cours / à vérifier chez un prestataire (pas de gel possible).
 * Une demande ou une approbation n'est PAS une exécution : un litige peut encore bloquer.
 */
final class FinancialPayoutExecution implements PayoutExecution
{
    public function executed(string $orderId): bool
    {
        return DB::table('financial_operations')->where('order_id', $orderId)->where('kind', 'payout')->whereIn('state', ['confirmed', 'in_progress', 'to_verify'])->exists();
    }
}
