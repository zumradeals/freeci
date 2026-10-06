<?php

namespace App\Modules\Finance;

use App\Integrations\Payments\PaymentMode;
use App\Modules\Orders\Models\Order;

/**
 * Porte de CRÉATION d'un nouveau paiement. Aucune restriction de personne (compte, freelance ou service de démonstration) : la séparation test / réel
 * porte sur la COMMANDE. Un nouveau paiement n'est possible que si :
 *  1. les nouveaux paiements sont ouverts (mode valide, autorisation explicite, configuration Genius Pay complète pour l'environnement actif) ;
 *  2. l'environnement de la commande (fixé À SA CRÉATION, immuable) correspond au mode actif : une commande de TEST ne devient jamais payable en argent réel,
 *     une commande réelle n'est jamais soldée par le bac à sable, une ancienne commande (environnement « legacy ») n'est jamais payable.
 * Le suivi des tentatives EXISTANTES ne dépend pas de cette porte.
 */
final class PaymentGate
{
    public function allows(Order $order): bool
    {
        return $this->denial($order) === null;
    }

    /** @return string|null motif technique du refus (jamais affiché tel quel à l'utilisateur) */
    public function denial(Order $order): ?string
    {
        if (($blocker = PaymentMode::blocker()) !== null) {
            return $blocker;
        }

        return $order->environment === PaymentMode::orderEnvironment() ? null : 'order_environment_mismatch';
    }
}
