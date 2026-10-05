<?php

namespace App\Shared;

use App\Modules\Orders\Exceptions\OperationKeyReused;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Idempotence des commandes (docs/02 §8.2) : une même clé d'opération (acteur + action + clé) ne produit qu'un
 * seul effet. Le reçu est écrit dans la même transaction que l'effet : tout ou rien.
 */
final class CommandReceipts
{
    /**
     * @param  Closure(): string  $do  exécute l'effet et retourne l'identifiant de la commande concernée
     * @return array{0: string, 1: bool} [identifiant de commande, vrai si c'est une répétition de la même opération]
     */
    public static function once(string $actorId, string $action, string $key, array $payload, Closure $do): array
    {
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($actorId, $action, $key, $hash, $do) {
            $inserted = DB::table('command_receipts')->insertOrIgnore([
                'actor_id' => $actorId, 'action' => $action, 'operation_key' => $key, 'request_hash' => $hash, 'created_at' => now(),
            ]);

            if ($inserted === 0) {
                // Répétition : un second envoi concurrent attend ici la validation du premier (index unique).
                $existing = DB::table('command_receipts')->where(['actor_id' => $actorId, 'action' => $action, 'operation_key' => $key])->first();
                if ($existing->request_hash !== $hash) {
                    throw new OperationKeyReused;
                }

                return [(string) $existing->order_id, true];
            }

            $orderId = $do();
            DB::table('command_receipts')->where(['actor_id' => $actorId, 'action' => $action, 'operation_key' => $key])->update(['order_id' => $orderId]);

            return [$orderId, false];
        });
    }
}
