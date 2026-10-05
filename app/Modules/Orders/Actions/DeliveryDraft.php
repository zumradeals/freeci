<?php

namespace App\Modules\Orders\Actions;

use App\Integrations\FileScan\FileScanner;
use App\Modules\Accounts\Models\User;
use App\Modules\Files\Actions\UploadBriefFile;
use App\Modules\Files\Enums\FileState;
use App\Modules\Files\Exceptions\FileForbidden;
use App\Modules\Files\Exceptions\FileRejected;
use App\Modules\Files\Exceptions\FilesDisabled;
use App\Modules\Files\Models\FileAsset;
use App\Modules\Orders\Enums\OrderState;
use App\Modules\Orders\Exceptions\InvalidTransition;
use App\Modules\Orders\Exceptions\OrderForbidden;
use App\Modules\Orders\Models\Delivery;
use App\Modules\Orders\Models\Order;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Brouillon de livraison (privé au freelance : rien n'apparaît dans l'historique partagé avant la soumission).
 * Les fichiers suivent la chaîne du lot 3 : stockage privé, quarantaine, contrôle ; un fichier non contrôlé n'est jamais examinable.
 */
final class DeliveryDraft
{
    public const MESSAGE_MAX = 4000;

    public function __construct(private FileScanner $scanner, private UploadBriefFile $validator) {}

    /** Verrouille la commande et retourne (en créant au besoin) le brouillon. */
    private function locked(User $freelancer, string $reference, callable $then): mixed
    {
        $order = Order::query()->where('reference', $reference)->where('freelancer_id', $freelancer->getKey())->first();
        if ($order === null) {
            throw new OrderForbidden;
        }

        return DB::transaction(function () use ($order, $freelancer, $then) {
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            if (! in_array($locked->state, [OrderState::InProgress, OrderState::RevisionRequested], true)) {
                throw new InvalidTransition;
            }
            $draft = Delivery::query()->where('order_id', $locked->getKey())->where('state', 'draft')->lockForUpdate()->first()
                ?? Delivery::create(['order_id' => $locked->getKey(), 'author_id' => $freelancer->getKey(), 'state' => 'draft']);

            return $then($locked, $draft);
        });
    }

    public function saveMessage(User $freelancer, string $reference, string $message): void
    {
        $this->locked($freelancer, $reference, fn ($o, Delivery $d) => $d->forceFill(['message' => mb_substr(trim($message), 0, self::MESSAGE_MAX)])->save());
    }

    /** @return string identifiant du fichier (état : quarantaine) */
    public function addFile(User $freelancer, string $reference, UploadedFile $file): string
    {
        if (! $this->scanner->isOperational()) {
            throw new FilesDisabled;
        }
        if (! $file->isValid()) {
            throw new FileRejected('Le téléversement a échoué : réessayez.');
        }
        $maxMb = (int) config('freeci.files.delivery_max_mb');
        [$name, $ext, $mime] = $this->validator->validateFile($file, $maxMb);

        return $this->locked($freelancer, $reference, function (Order $order, Delivery $draft) use ($file, $name, $ext, $mime, $freelancer) {
            $active = FileAsset::query()->where('delivery_id', $draft->getKey())->whereNotIn('state', [FileState::Rejected->value, FileState::Removed->value]);
            if ((clone $active)->count() >= (int) config('freeci.files.max_files')) {
                throw new FileRejected('Nombre maximal de fichiers atteint ('.config('freeci.files.max_files').').');
            }
            if ((clone $active)->sum('size_bytes') + $file->getSize() > (int) config('freeci.files.max_total_mb') * 1048576) {
                throw new FileRejected('Taille totale maximale atteinte ('.config('freeci.files.max_total_mb').' Mo).');
            }
            $key = 'o/'.substr($id = (string) Str::uuid(), 0, 2).'/'.$id;
            Storage::disk('private_files')->putFileAs(dirname($key), $file, basename($key));

            return FileAsset::create([
                'order_id' => $order->getKey(), 'delivery_id' => $draft->getKey(), 'uploader_id' => $freelancer->getKey(), 'original_name' => $name, 'extension' => $ext,
                'detected_mime' => $mime, 'size_bytes' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getRealPath()),
                'storage_key' => $key, 'state' => FileState::Quarantined,
            ])->getKey();
        });
    }

    public function removeFile(User $freelancer, string $reference, string $fileId): void
    {
        $this->locked($freelancer, $reference, function (Order $order, Delivery $draft) use ($fileId) {
            $f = FileAsset::query()->whereKey($fileId)->where('delivery_id', $draft->getKey())->lockForUpdate()->first();
            if ($f === null || $f->state === FileState::Removed) {
                throw new FileForbidden;
            }
            $f->forceFill(['state' => FileState::Removed, 'removed_at' => now()])->save();
            Storage::disk('private_files')->delete($f->storage_key);
        });
    }

    /**
     * Ce qui empêche la soumission, en clair (liste vide = livraison soumissible). Lecture seule, sans verrou.
     *
     * @return list<string>
     */
    public static function blockers(Order $order, ?Delivery $draft): array
    {
        $order->loadMissing('agreement');
        $reasons = [];
        if ($draft === null || trim((string) $draft->message) === '') {
            $reasons[] = 'Rédigez le message de livraison.';
        }
        $files = $draft === null ? collect() : $draft->files()->whereNotIn('state', [FileState::Removed->value])->get();
        $pending = $files->whereIn('state', [FileState::Quarantined, FileState::Scanning])->count();
        $clean = $files->where('state', FileState::Clean)->count();
        if ($pending > 0) {
            $reasons[] = $pending.' fichier'.($pending > 1 ? 's' : '').' en cours de contrôle de sécurité : la livraison ne peut pas être soumise tant que le contrôle n’est pas terminé.';
        }
        if ($order->agreement->delivery_requires_files && $clean === 0) {
            $reasons[] = 'L’accord prévoit des fichiers livrables : ajoutez au moins un fichier ayant passé le contrôle de sécurité.';
        }
        if ($files->where('state', FileState::Rejected)->count() > 0) {
            $reasons[] = 'Un fichier a été refusé par le contrôle de sécurité : il ne sera pas livré (retirez-le de la liste).';
        }

        return $reasons;
    }
}
