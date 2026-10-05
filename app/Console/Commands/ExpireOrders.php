<?php

namespace App\Console\Commands;

use App\Modules\Missions\Actions\MissionLifecycle;
use App\Modules\Orders\Actions\ExpireOverdueOrders;
use App\Modules\Orders\Actions\RecordReviewFollowUps;
use Illuminate\Console\Command;

class ExpireOrders extends Command
{
    protected $signature = 'freeci:orders:expire';

    protected $description = 'Expire les demandes dont le délai est dépassé et enregistre les besoins de suivi après délai d’examen (les actions et les listes le font aussi à la volée).';

    public function handle(ExpireOverdueOrders $expire, RecordReviewFollowUps $followUps, MissionLifecycle $missions): int
    {
        $this->info($missions->expireOverdue().' mission(s) expirée(s) (période de sélection dépassée).');
        $this->info($expire().' commande(s) expirée(s).');
        $this->info($followUps().' besoin(s) de suivi enregistré(s) (délai d’examen dépassé ; aucune validation automatique).');

        return self::SUCCESS;
    }
}
