<?php

namespace App\Console\Commands;

use App\Modules\Catalog\Actions\AvailabilityManager;
use Illuminate\Console\Command;

class AvailabilityReopen extends Command
{
    protected $signature = 'freeci:availability:reopen';

    protected $description = 'Constate les dates de retour échues des freelances (retour automatique) et les prévient. La disponibilité est de toute façon évaluée à la lecture.';

    public function handle(AvailabilityManager $availability): int
    {
        $this->info($availability->reopenDue().' freelance(s) de nouveau disponible(s).');

        return self::SUCCESS;
    }
}
