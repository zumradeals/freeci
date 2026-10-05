<?php

namespace App\Console\Commands;

use App\Modules\Files\Actions\ScanBriefFile;
use App\Modules\Files\Enums\FileState;
use App\Modules\Files\Models\FileAsset;
use Illuminate\Console\Command;

class FilesScan extends Command
{
    protected $signature = 'freeci:files:scan';

    protected $description = 'Reprend le contrôle de sécurité des fichiers restés en quarantaine (service d\'analyse indisponible au moment du dépôt).';

    public function handle(ScanBriefFile $scan): int
    {
        $ids = FileAsset::query()->where(fn ($q) => $q->where('state', FileState::Quarantined->value)->orWhere(fn ($w) => $w->where('state', FileState::Scanning->value)->where('updated_at', '<', now()->subMinutes(5))))
            ->where('scan_attempts', '<', 50)->pluck('id');
        $r = ['clean' => 0, 'rejected' => 0, 'unavailable' => 0, 'skipped' => 0];
        foreach ($ids as $id) {
            $r[$scan($id)]++;
        }
        $this->info(sprintf('%d fichier(s) traité(s) : %d propre(s), %d refusé(s), %d toujours bloqué(s).', $ids->count(), $r['clean'], $r['rejected'], $r['unavailable']));

        return self::SUCCESS;
    }
}
