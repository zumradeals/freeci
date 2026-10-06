<?php

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\LedgerBatch;
use App\Modules\Finance\Support\FinancialPolicy;
use Illuminate\Support\Facades\DB;

/**
 * Écritures du registre existant (lots équilibrés, en ajout seul, un lot par `event_key`). Aucune modification ni suppression d'écriture : une erreur ou un
 * échec se corrige par une écriture CORRECTRICE liée au lot corrigé (`reverses_batch_id`), jamais par un UPDATE.
 * À appeler dans la transaction qui verrouille la commande.
 */
final class FinanceLedger
{
    /**
     * @param  array<string, int>  $lines  [compte de base => montant signé] ; la somme doit être nulle
     */
    public function post(string $eventKey, string $orderId, ?string $paymentId, string $kind, bool $simulated, array $lines, ?string $operationId = null, ?string $reversesBatchId = null, ?string $memo = null): LedgerBatch
    {
        if (array_sum($lines) !== 0 || count($lines) < 2) {
            throw new \LogicException('Lot du registre non équilibré.');
        }
        $batch = LedgerBatch::query()->firstOrCreate(['event_key' => $eventKey], [
            'order_id' => $orderId, 'payment_id' => $paymentId, 'kind' => $kind, 'is_simulated' => $simulated, 'occurred_at' => now(),
            'operation_id' => $operationId, 'reverses_batch_id' => $reversesBatchId, 'memo' => $memo === null ? null : mb_substr($memo, 0, 200),
        ]);
        if ($batch->wasRecentlyCreated) {
            $batch->lines()->createMany(array_map(fn ($account, $amount) => ['account' => FinancialPolicy::account($account, $simulated), 'amount_xof' => $amount], array_keys($lines), array_values($lines)));
        }

        return $batch;
    }

    /** Écriture correctrice : exactement l'inverse du lot corrigé, liée à lui (une seule correction par lot). */
    public function reverse(string $batchEventKey, string $eventKey, ?string $operationId, string $memo): ?LedgerBatch
    {
        $original = LedgerBatch::query()->where('event_key', $batchEventKey)->first();
        if ($original === null) {
            return null;
        }
        $already = LedgerBatch::query()->where('reverses_batch_id', $original->getKey())->first();
        if ($already !== null) {
            return $already;
        }
        $batch = LedgerBatch::query()->create([
            'event_key' => $eventKey, 'order_id' => $original->order_id, 'payment_id' => $original->payment_id, 'kind' => $original->kind.'_reversed', 'is_simulated' => $original->is_simulated,
            'occurred_at' => now(), 'operation_id' => $operationId, 'reverses_batch_id' => $original->getKey(), 'memo' => mb_substr($memo, 0, 200),
        ]);
        $batch->lines()->createMany(DB::table('ledger_lines')->where('batch_id', $original->getKey())->get()->map(fn ($l) => ['account' => $l->account, 'amount_xof' => -$l->amount_xof])->all());

        return $batch;
    }
}
