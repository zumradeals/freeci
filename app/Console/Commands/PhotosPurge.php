<?php

namespace App\Console\Commands;

use App\Modules\Accounts\Actions\ProfilePhotos;
use Illuminate\Console\Command;

class PhotosPurge extends Command
{
    protected $signature = 'freeci:photos:purge';

    protected $description = 'Efface les fichiers des photos de profil retirées par l\'administration une fois le délai de conservation écoulé (sauf dossier d\'assistance ouvert).';

    public function handle(ProfilePhotos $photos): int
    {
        $this->info($photos->purgeExpired().' photo(s) retirée(s) effacée(s).');

        return self::SUCCESS;
    }
}
