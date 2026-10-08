<?php

namespace App\Modules\Accounts\Queries;

use App\Modules\Accounts\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Qui peut lire une photo de profil. Photo ACTIVE seulement :
 *  - publique si la personne a un profil freelance publié ;
 *  - sinon (client, freelance non publié) : elle-même, une personne avec qui elle a une commande ou une conversation, et les administrateurs/le support.
 * Une photo retirée par l'administration n'est lisible que par le personnel habilité, tant que le fichier est conservé.
 */
final class ProfilePhotoAccess
{
    /** @return array{path: string, mime: string, public: bool}|null */
    public function __invoke(string $id, string $variant, ?User $viewer): ?array
    {
        if (! in_array($variant, ['large', 'small'], true)) {
            return null;
        }
        $photo = DB::table('profile_photos')->where('id', $id)->first();
        if ($photo === null) {
            return null;
        }
        $key = $variant === 'small' ? $photo->key_small : $photo->key_large;
        if ($key === null) {
            return null;
        }
        $public = false;
        if ($photo->state === 'active') {
            $public = DB::table('freelance_profiles')->where('user_id', $photo->user_id)->whereNotNull('published_at')->exists();
            if (! $public && ! $this->mayView($photo->user_id, $viewer)) {
                return null;
            }
        } elseif ($photo->state !== 'removed' || $viewer === null || ! $viewer->isStaff()) {
            return null;
        }
        $disk = Storage::disk('private_files');

        return $disk->exists($key) ? ['path' => $disk->path($key), 'mime' => $photo->mime, 'public' => $public] : null;
    }

    private function mayView(string $ownerId, ?User $viewer): bool
    {
        if ($viewer === null) {
            return false;
        }
        $v = $viewer->getKey();

        if ($v === $ownerId || $viewer->isStaff()) {
            return true;
        }
        $between = fn ($table) => DB::table($table)->where(fn ($q) => $q->where('client_id', $v)->where('freelancer_id', $ownerId))->orWhere(fn ($q) => $q->where('client_id', $ownerId)->where('freelancer_id', $v))->exists();

        return $between('orders') || $between('conversations');         // une commande ou une conversation en commun
    }
}
