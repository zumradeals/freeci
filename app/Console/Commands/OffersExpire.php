<?php

namespace App\Console\Commands;

use App\Modules\Orders\Actions\CustomOffers;
use Illuminate\Console\Command;

class OffersExpire extends Command
{
    protected $signature = 'freeci:offers:expire';

    protected $description = 'Constate les offres personnalisées dont la date de validité est passée et prévient les deux personnes. La validité est de toute façon évaluée à chaque lecture.';

    public function handle(CustomOffers $offers): int
    {
        $this->info($offers->expireDue().' offre(s) expirée(s).');

        return self::SUCCESS;
    }
}
