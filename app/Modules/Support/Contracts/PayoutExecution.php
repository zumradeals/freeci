<?php

namespace App\Modules\Support\Contracts;

/**
 * Le reversement de cette commande a-t-il DÉJÀ été exécuté (accepté par le prestataire de paiement) ?
 * Distingue le LITIGE (reversement non exécuté : il le bloque) de la RÉCLAMATION (reversement déjà envoyé : aucun blocage possible).
 * Le futur module financier fournit la vraie implémentation ; tant qu'il n'existe pas, aucun reversement n'est exécuté.
 */
interface PayoutExecution
{
    public function executed(string $orderId): bool;
}
