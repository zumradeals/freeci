<?php

namespace App\Modules\Accounts\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Models\FreelanceProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Rend le profil PUBLIC (page /freelances/{adresse}) quand il est complet : activité, présentation, ville, au moins une compétence.
 * C'est une visibilité décidée par le propriétaire, pas une vérification : aucun badge n'est attribué par cette action.
 */
final class PublishFreelanceProfile
{
    /** @return list<string> éléments manquants (vide = complet) */
    public static function missing(FreelanceProfile $p): array
    {
        $min = config('freeci.catalog.bio')[0];
        $m = [];
        if (mb_strlen(trim($p->headline)) < 5) {
            $m[] = 'Votre activité';
        }
        if (mb_strlen(trim((string) $p->bio)) < $min) {
            $m[] = "Une présentation d’au moins {$min} caractères";
        }
        if (trim((string) $p->city) === '') {
            $m[] = 'Votre ville';
        }
        if (count($p->skills ?? []) < 1) {
            $m[] = 'Au moins une compétence';
        }

        return $m;
    }

    public function __invoke(User $user): FreelanceProfile
    {
        return DB::transaction(function () use ($user) {
            $p = FreelanceProfile::query()->where('user_id', $user->getKey())->lockForUpdate()->first();
            if ($p === null || ! $user->hasRole('freelance')) {
                throw ValidationException::withMessages(['profile' => 'Activez d’abord votre espace freelance.']);
            }
            $missing = self::missing($p);
            if ($missing !== []) {
                throw ValidationException::withMessages(['profile' => 'Complétez votre profil avant de le publier : '.implode(', ', array_map('mb_strtolower', $missing)).'.']);
            }
            if ($p->published_at === null) {
                $p->forceFill(['published_at' => now()])->save();
            }

            return $p;
        });
    }
}
