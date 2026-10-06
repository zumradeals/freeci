<?php

namespace App\Modules\Support\Support;

use App\Modules\Support\Contracts\PayoutExecution;

/** Aucun module de reversement n'existe encore : aucun reversement n'a été exécuté. À remplacer par l'implémentation financière. */
final class NoPayoutExecution implements PayoutExecution
{
    public function executed(string $orderId): bool
    {
        return false;
    }
}
