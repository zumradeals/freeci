<?php

namespace App\Modules\Accounts\Queries;

use App\Modules\Accounts\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Qui peut lire l'image d'une réalisation : tout le monde si elle est en ligne et que le profil est publié ; sinon son auteur et le personnel.
 * Une réalisation retirée par l'administration n'est lisible que par le personnel, tant que le fichier est conservé.
 */
final class PortfolioAccess
{
    /** @return array{path: string, mime: string, public: bool}|null */
    public function __invoke(string $id, string $variant, ?User $viewer): ?array
    {
        if (! in_array($variant, ['large', 'card'], true)) {
            return null;
        }
        $item = DB::table('portfolio_items')->where('id', $id)->first();
        $key = $item === null ? null : ($variant === 'card' ? $item->key_card : $item->key_large);
        if ($key === null) {
            return null;
        }
        $public = false;
        if ($item->state === 'active') {
            $public = DB::table('freelance_profiles')->where('user_id', $item->user_id)->whereNotNull('published_at')->exists();
            if (! $public && ($viewer === null || ($viewer->getKey() !== $item->user_id && ! $viewer->isStaff()))) {
                return null;
            }
        } elseif ($item->state !== 'removed' || $viewer === null || ! $viewer->isStaff()) {
            return null;
        }
        $disk = Storage::disk('private_files');

        return $disk->exists($key) ? ['path' => $disk->path($key), 'mime' => $item->mime, 'public' => $public] : null;
    }
}
