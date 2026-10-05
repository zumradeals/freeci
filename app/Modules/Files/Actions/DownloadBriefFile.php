<?php

namespace App\Modules\Files\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Files\Enums\FileState;
use App\Modules\Files\Exceptions\FileForbidden;
use App\Modules\Files\Models\FileAsset;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * `Files\IssueDownload` : seul un fichier CONTRÔLÉ (état « clean ») d'une commande dont on est l'une des deux parties est
 * téléchargeable. Lien court signé, lié à l'utilisateur ; jamais de clé de stockage exposée.
 */
final class DownloadBriefFile
{
    public function link(User $viewer, string $reference, string $fileId): string
    {
        $this->find($viewer, $reference, $fileId);

        return URL::temporarySignedRoute('orders.files.download', now()->addMinutes(5), ['reference' => $reference, 'file' => $fileId, 'u' => $viewer->getKey()]);
    }

    /** @return array{path: string, name: string} */
    public function open(User $viewer, string $reference, string $fileId): array
    {
        $f = $this->find($viewer, $reference, $fileId);
        if (! Storage::disk('private_files')->exists($f->storage_key)) {
            throw new FileForbidden;
        }

        return ['path' => Storage::disk('private_files')->path($f->storage_key), 'name' => $f->original_name];
    }

    private function find(User $viewer, string $reference, string $fileId): FileAsset
    {
        $order = Order::query()->where('reference', $reference)
            ->where(fn ($q) => $q->where('client_id', $viewer->getKey())->orWhere('freelancer_id', $viewer->getKey()))->first();
        $file = $order === null ? null : FileAsset::query()->whereKey($fileId)->where('order_id', $order->getKey())->first();
        if ($file === null || $file->state !== FileState::Clean) {
            throw new FileForbidden;          // inexistant, d'autrui ou non contrôlé : même réponse
        }

        return $file;
    }
}
