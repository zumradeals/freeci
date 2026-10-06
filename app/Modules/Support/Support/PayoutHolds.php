<?php

namespace App\Modules\Support\Support;

use Illuminate\Support\Facades\DB;

/**
 * Blocage INTERNE des reversements d'une commande faisant l'objet d'un litige ou d'une demande d'annulation. Ce n'est PAS un blocage
 * réalisé chez un prestataire de paiement. Contrat pour le futur module financier : `Finance\InitiatePayout` DOIT refuser toute
 * opération tant que `isHeld($orderId)` est vrai, sous le même verrou de commande que l'ouverture du litige.
 */
final class PayoutHolds
{
    public static function isHeld(string $orderId): bool
    {
        return DB::table('payout_holds')->where('order_id', $orderId)->whereNull('released_at')->exists();
    }
}
