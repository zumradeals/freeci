<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Enums\ServiceStatus;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Models\ServiceMedia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Qui peut lire une image de service. Public : seulement si l'image figure dans la version PUBLIÉE d'un service en ligne.
 * Propriétaire : toujours (brouillon, aperçu). Tout autre cas : refusé (le contrôleur répond 404).
 */
final class ServiceMediaAccess
{
    /** @return array{path: string, mime: string, public: bool}|null */
    public function __invoke(string $id, string $variant, ?User $viewer): ?array
    {
        $media = ServiceMedia::query()->whereKey($id)->first();
        if ($media === null || ! in_array($variant, ['large', 'card'], true)) {
            return null;
        }
        $public = Service::query()->whereKey($media->service_id)->where('status', ServiceStatus::Published->value)->whereRaw('images @> ?::jsonb', [json_encode([['id' => $id]])])->exists();
        $owner = ! $public && $viewer !== null && DB::table('services')->join('freelance_profiles', 'freelance_profiles.id', '=', 'services.freelance_profile_id')
            ->where('services.id', $media->service_id)->where('freelance_profiles.user_id', $viewer->getKey())->exists();
        if (! $public && ! $owner) {
            return null;
        }
        $key = $variant === 'card' ? $media->key_card : $media->key_large;

        return Storage::disk('private_files')->exists($key) ? ['path' => Storage::disk('private_files')->path($key), 'mime' => $media->mime, 'public' => $public] : null;
    }
}
