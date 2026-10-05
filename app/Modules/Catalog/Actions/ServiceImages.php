<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Exceptions\ServiceStateConflict;
use App\Modules\Catalog\Models\ServiceMedia;
use App\Modules\Catalog\Support\ImageProcessor;
use App\Modules\Files\Exceptions\FileRejected;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Images de la version de travail d'un service : dépôt validé et réencodé, retrait de la liste. Jamais d'écriture dans `public/`. */
final class ServiceImages
{
    public function __construct(private ServiceAuthoring $authoring, private ImageProcessor $processor) {}

    public function add(User $owner, string $serviceId, UploadedFile $file, string $alt, int $revisionNo): string
    {
        $alt = trim($alt);
        if (mb_strlen($alt) < 3 || mb_strlen($alt) > 160) {
            throw new FileRejected('Décrivez l’image en 3 à 160 caractères (texte alternatif).');
        }
        $this->authoring->owned($owner, $serviceId);           // refuse avant tout traitement
        $out = $this->processor->process($file);

        return DB::transaction(function () use ($owner, $serviceId, $out, $alt, $revisionNo) {
            $service = $this->authoring->owned($owner, $serviceId, lock: true);
            $this->authoring->ensureVersions($service);
            $v = $this->authoring->working($service) ?? throw new ServiceStateConflict('Aucune version en cours de rédaction.');
            if (! $v->isEditable() || $v->revision_no !== $revisionNo) {
                throw new ServiceStateConflict('Cette version a changé ou est en contrôle : rechargez la page.');
            }
            if (count($v->images) >= (int) config('freeci.catalog.images_max')) {
                throw new FileRejected('Au plus '.config('freeci.catalog.images_max').' images par service.');
            }
            $id = (string) Str::uuid();
            $dir = 'm/'.substr($id, 0, 2);
            $disk = Storage::disk('private_files');
            $disk->put("{$dir}/{$id}-l.webp", $out['large']);
            $disk->put("{$dir}/{$id}-c.webp", $out['card']);
            ServiceMedia::create(['id' => $id, 'service_id' => $service->getKey(), 'uploader_id' => $owner->getKey(), 'mime' => $out['mime'], 'width' => $out['width'], 'height' => $out['height'],
                'size_bytes' => strlen($out['large']), 'sha256' => $out['sha256'], 'key_large' => "{$dir}/{$id}-l.webp", 'key_card' => "{$dir}/{$id}-c.webp"]);
            $images = $v->images;
            $images[] = ['id' => $id, 'alt' => $alt, 'caption' => ''];
            $v->forceFill(['images' => $images, 'revision_no' => $v->revision_no + 1])->save();

            return $id;
        });
    }

    public function remove(User $owner, string $serviceId, string $mediaId, int $revisionNo): void
    {
        DB::transaction(function () use ($owner, $serviceId, $mediaId, $revisionNo) {
            $service = $this->authoring->owned($owner, $serviceId, lock: true);
            $v = $this->authoring->working($service) ?? throw new ServiceStateConflict('Aucune version en cours de rédaction.');
            if (! $v->isEditable() || $v->revision_no !== $revisionNo) {
                throw new ServiceStateConflict('Cette version a changé ou est en contrôle : rechargez la page.');
            }
            $images = array_values(array_filter($v->images, fn ($i) => ($i['id'] ?? null) !== $mediaId));
            $v->forceFill(['images' => $images, 'revision_no' => $v->revision_no + 1])->save();       // le fichier reste tant qu'une autre version le référence (nettoyage : freeci:media:prune)
        });
    }
}
