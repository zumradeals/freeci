<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Exceptions\ServiceNotFound;
use App\Modules\Catalog\Models\FreelanceProfile;
use App\Modules\Catalog\Models\Service;
use Illuminate\Support\Facades\DB;

/**
 * `Catalog\ToggleFavorite` (F13) : favori PRIVÉ (service ou freelance), sans doublon (index unique). On ne peut AJOUTER qu'un contenu effectivement public ;
 * on peut toujours RETIRER. L'intention est explicite (« add » / « remove ») : un double clic ne bascule pas l'état.
 */
final class ToggleFavorite
{
    /** @param 'add'|'remove' $intent */
    public function __invoke(User $user, string $kind, string $slug, string $intent): bool
    {
        $id = $this->resolve($kind, $slug, $intent === 'add');
        if ($intent === 'remove') {
            if ($id !== null) {
                DB::table('favorites')->where('user_id', $user->getKey())->where('kind', $kind)->where('target_id', $id)->delete();
            }

            return false;
        }
        DB::table('favorites')->insertOrIgnore(['user_id' => $user->getKey(), 'kind' => $kind, 'target_id' => $id, 'created_at' => now()]);

        return true;
    }

    private function resolve(string $kind, string $slug, bool $mustBePublic): ?string
    {
        if ($kind === 'service') {
            $q = Service::query()->where('slug', $slug);
            $s = ($mustBePublic ? $q->published() : $q)->first();

            return $s?->getKey() ?? ($mustBePublic ? throw new ServiceNotFound : null);
        }
        $q = FreelanceProfile::query()->where('slug', $slug);
        $p = ($mustBePublic ? $q->whereNotNull('published_at') : $q)->first();

        return $p?->getKey() ?? ($mustBePublic ? throw new ServiceNotFound : null);
    }
}
