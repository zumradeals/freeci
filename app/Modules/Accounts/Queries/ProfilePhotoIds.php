<?php

namespace App\Modules\Accounts\Queries;

use Illuminate\Support\Facades\DB;

/**
 * Identifiant de la photo ACTIVE d'une personne (ou null) : c'est tout ce que les écrans reçoivent ; l'accès réel est revérifié à chaque
 * lecture de l'image (voir ProfilePhotoAccess). Mémorisé pour la durée de la requête.
 */
final class ProfilePhotoIds
{
    /** @var array<string, ?string> */
    private array $memo = [];

    public function for(?string $userId): ?string
    {
        if ($userId === null) {
            return null;
        }
        if (! array_key_exists($userId, $this->memo)) {
            $this->memo[$userId] = DB::table('profile_photos')->where('user_id', $userId)->where('state', 'active')->value('id');
        }

        return $this->memo[$userId];
    }

    /** @param list<string> $userIds */
    public function preload(array $userIds): void
    {
        $missing = array_values(array_diff(array_unique($userIds), array_keys($this->memo)));
        if ($missing === []) {
            return;
        }
        $found = DB::table('profile_photos')->whereIn('user_id', $missing)->where('state', 'active')->pluck('id', 'user_id')->all();
        foreach ($missing as $u) {
            $this->memo[$u] = $found[$u] ?? null;
        }
    }

    public function forget(string $userId): void
    {
        unset($this->memo[$userId]);
    }

    /** Photo d'un freelance à partir de l'identifiant de son profil (cartes de services). */
    public function forProfile(?string $profileId): ?string
    {
        $uid = $profileId === null ? null : DB::table('freelance_profiles')->where('id', $profileId)->value('user_id');

        return $this->for($uid);
    }
}
