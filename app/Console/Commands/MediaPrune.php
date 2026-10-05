<?php

namespace App\Console\Commands;

use App\Modules\Catalog\Models\ServiceMedia;
use App\Modules\Catalog\Models\ServiceVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MediaPrune extends Command
{
    protected $signature = 'freeci:media:prune';

    protected $description = 'Supprime les images de service qu\'aucune version ne référence plus (et déposées depuis plus de 24 h).';

    public function handle(): int
    {
        $referenced = [];
        ServiceVersion::query()->select('images')->cursor()->each(function ($v) use (&$referenced) {
            foreach ($v->images as $i) {
                if (isset($i['id'])) {
                    $referenced[$i['id']] = true;
                }
            }
        });
        $n = 0;
        ServiceMedia::query()->where('created_at', '<', now()->subDay())->cursor()->each(function (ServiceMedia $m) use ($referenced, &$n) {
            if (! isset($referenced[$m->id])) {
                Storage::disk('private_files')->delete([$m->key_large, $m->key_card]);
                $m->delete();
                $n++;
            }
        });
        $this->info("{$n} image(s) orpheline(s) supprimée(s).");

        return self::SUCCESS;
    }
}
