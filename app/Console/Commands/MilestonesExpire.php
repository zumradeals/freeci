<?php

namespace App\Console\Commands;

use App\Modules\Missions\Actions\MissionPlans;
use Illuminate\Console\Command;

/** Plans de jalons en pause dont le délai de reprise est dépassé : arrêtés, jalons restants jamais dus (F-13). Idempotent. */
class MilestonesExpire extends Command
{
    protected $signature = 'freeci:milestones:expire';

    protected $description = 'Arrête les plans de jalons dont le paiement n’a pas été repris dans le délai';

    public function handle(MissionPlans $plans): int
    {
        $n = $plans->expireDue();
        $this->info($n === 0 ? 'Aucun plan à arrêter.' : "{$n} plan(s) arrêté(s).");

        return self::SUCCESS;
    }
}
