<?php

namespace App\Modules\Accounts\Actions;

use App\Modules\Accounts\Models\AccountRole;
use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Models\FreelanceProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Active l'espace freelance et enregistre le profil minimal (nom affiché, titre, ville). Idempotent.
 * Le profil ne se « publie » pas ici : il n'apparaît que sur les services publiés par ailleurs (lot ultérieur).
 */
final class SaveFreelanceProfile
{
    public function __invoke(User $user, string $displayName, string $headline, ?string $city): FreelanceProfile
    {
        $displayName = trim($displayName);
        $headline = trim($headline);
        $city = $city === null ? null : trim($city);
        $errors = [];
        if (mb_strlen($displayName) < 2 || mb_strlen($displayName) > 120) {
            $errors['display_name'] = 'Indiquez un nom de 2 à 120 caractères.';
        }
        if (mb_strlen($headline) < 5 || mb_strlen($headline) > 160) {
            $errors['headline'] = 'Décrivez votre activité en 5 à 160 caractères (ex. « Dessinateur DAO »).';
        }
        if ($city !== null && mb_strlen($city) > 80) {
            $errors['city'] = 'Saisissez au plus 80 caractères.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($user, $displayName, $headline, $city) {
            $user->roles()->firstOrCreate(['role' => AccountRole::FREELANCE]);

            return FreelanceProfile::updateOrCreate(
                ['user_id' => $user->getKey()],
                ['display_name' => $displayName, 'headline' => $headline, 'city' => $city === '' ? null : $city, 'is_demo' => $user->is_demo],
            );
        });
    }
}
