<?php

namespace App\Modules\Files\Actions;

use App\Integrations\FileScan\FileScanner;
use App\Integrations\FileScan\ScanResult;
use App\Modules\Files\Enums\FileState;
use App\Modules\Files\Models\FileAsset;
use App\Modules\Orders\Actions\StartOrderIfReady;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Contrôle de sécurité d'un fichier en quarantaine. « Indisponible » ne rend JAMAIS un fichier propre : il reste bloqué.
 * Un fichier devenu propre peut compléter le brief et déclencher le démarrage (une seule fois, commande verrouillée).
 */
final class ScanBriefFile
{
    public function __construct(private FileScanner $scanner, private StartOrderIfReady $start) {}

    /** @return string 'clean' | 'rejected' | 'unavailable' | 'skipped' */
    public function __invoke(string $fileId): string
    {
        $claimed = DB::transaction(function () use ($fileId) {
            $f = FileAsset::query()->whereKey($fileId)->lockForUpdate()->first();
            $stale = $f?->state === FileState::Scanning && $f->updated_at->lt(now()->subMinutes(5));
            if ($f === null || ($f->state !== FileState::Quarantined && ! $stale)) {
                return null;
            }
            $f->forceFill(['state' => FileState::Scanning, 'scan_attempts' => $f->scan_attempts + 1])->save();

            return $f;
        });
        if ($claimed === null) {
            return 'skipped';
        }

        $path = Storage::disk('private_files')->path($claimed->storage_key);
        $result = $this->scanner->scan($path);

        return DB::transaction(function () use ($claimed, $result) {
            $order = Order::query()->whereKey($claimed->order_id)->lockForUpdate()->firstOrFail();      // ordre : commande, puis fichier
            $f = FileAsset::query()->whereKey($claimed->getKey())->lockForUpdate()->firstOrFail();
            if ($f->state !== FileState::Scanning) {
                return 'skipped';
            }

            if ($result === ScanResult::Clean) {
                $f->forceFill(['state' => FileState::Clean, 'scanned_at' => now(), 'last_scan_error' => null])->save();
                if ($f->delivery_id === null) {                       // un brouillon de livraison reste privé au freelance : pas d'historique partagé
                    $order->events()->create(['type' => 'brief_file_clean', 'actor_id' => null, 'note' => $f->original_name]);
                    ($this->start)($order);
                }

                return 'clean';
            }
            if ($result === ScanResult::Infected) {
                $f->forceFill(['state' => FileState::Rejected, 'rejection_reason' => 'malware_detected', 'scanned_at' => now()])->save();
                Storage::disk('private_files')->delete($f->storage_key);
                if ($f->delivery_id === null) {
                    $order->events()->create(['type' => 'brief_file_rejected', 'actor_id' => null, 'note' => $f->original_name]);
                }

                return 'rejected';
            }
            $f->forceFill(['state' => FileState::Quarantined, 'last_scan_error' => 'scanner_unavailable'])->save();   // reste bloqué

            return 'unavailable';
        });
    }
}
