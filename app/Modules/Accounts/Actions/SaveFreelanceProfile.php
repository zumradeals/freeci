<?php

namespace App\Modules\Accounts\Actions;

use App\Modules\Accounts\Models\AccountRole;
use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Models\FreelanceProfile;
use App\Modules\Catalog\Support\PrivateContact;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Active l'espace freelance et enregistre le profil : nom affiché, activité, ville, présentation, compétences. Idempotent.
 * Seuls ces champs sont écrits : jamais de badge, de vérification, de rôle, de publication ni d'adresse (liste blanche).
 * Le profil ne devient public que par `PublishFreelanceProfile`, quand il est complet.
 */
final class SaveFreelanceProfile
{
    /** @param  string|list<string>|null  $skills  null = inchangées ; texte = séparées par des virgules ou des retours à la ligne */
    public function __invoke(User $user, string $displayName, string $headline, ?string $city, ?string $bio = null, string|array|null $skills = null): FreelanceProfile
    {
        $displayName = trim($displayName);
        $headline = trim($headline);
        $city = $city === null ? null : trim($city);
        $c = config('freeci.catalog');
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
        if ($bio !== null) {
            $bio = trim($bio);
            if (mb_strlen($bio) > $c['bio'][1]) {
                $errors['bio'] = 'La présentation fait au plus '.$c['bio'][1].' caractères.';
            }
        }
        $list = null;
        if ($skills !== null) {
            $raw = is_array($skills) ? $skills : preg_split('/[,\n;]+/u', $skills);
            $list = array_values(array_unique(array_filter(array_map(fn ($s) => trim((string) preg_replace('/\s+/u', ' ', $s)), $raw), fn ($s) => $s !== '')));
            if (count($list) > $c['skills_max'] || collect($list)->contains(fn ($s) => mb_strlen($s) < $c['skill'][0] || mb_strlen($s) > $c['skill'][1])) {
                $errors['skills'] = 'Indiquez au plus '.$c['skills_max'].' compétences, de '.$c['skill'][0].' à '.$c['skill'][1].' caractères chacune, séparées par des virgules.';
            }
        }
        foreach (['display_name' => $displayName, 'headline' => $headline, 'bio' => $bio ?? '', 'skills' => implode(' ', $list ?? [])] as $field => $text) {
            if (! isset($errors[$field]) && PrivateContact::found($text)) {
                $errors[$field] = 'Retirez les coordonnées privées (adresse e-mail, numéro de téléphone) : ce texte est public.';
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($user, $displayName, $headline, $city, $bio, $list) {
            $user->roles()->firstOrCreate(['role' => AccountRole::FREELANCE]);
            $profile = FreelanceProfile::query()->where('user_id', $user->getKey())->lockForUpdate()->first() ?? new FreelanceProfile(['user_id' => $user->getKey()]);
            $profile->fill(['display_name' => $displayName, 'headline' => $headline, 'city' => $city === '' ? null : $city, 'is_demo' => $user->is_demo]);
            if ($bio !== null) {
                $profile->bio = $bio === '' ? null : $bio;
            }
            if ($list !== null) {
                $profile->skills = $list;
            }
            if ($profile->slug === null) {
                $profile->slug = Str::limit(Str::slug($displayName) ?: 'freelance', 120, '').'-'.Str::lower(Str::random(6));      // adresse publique stable, jamais modifiée ensuite
            }
            $profile->save();

            return $profile;
        });
    }
}
