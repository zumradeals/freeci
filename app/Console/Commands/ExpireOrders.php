<?php

namespace App\Console\Commands;

use App\Modules\Orders\Actions\ExpireOverdueOrders;
use Illuminate\Console\Command;

class ExpireOrders extends Command
{
    protected $signature = 'freeci:orders:expire';

    protected $description = 'Expire les demandes dont le délai de réponse est dépassé (les actions et les listes le font aussi à la volée).';

    public function handle(ExpireOverdueOrders $expire): int
    {
        $this->info($expire().' commande(s) expirée(s).');

        return self::SUCCESS;
    }
}
