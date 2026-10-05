<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Data\ServiceCard;
use App\Modules\Catalog\Exceptions\ServiceNotFound;
use App\Modules\Catalog\Models\FreelanceProfile;
use App\Modules\Catalog\Models\Service;

/**
 * Profil PUBLIC : uniquement les champs publics (jamais l'adresse e-mail ni l'identifiant du compte) et les services effectivement
 * publiés. Un profil non publié répond comme un profil inexistant.
 */
final class GetPublicProfile
{
    /** @return array{name:string,initials:string,headline:string,city:?string,bio:?string,skills:list<string>,isDemo:bool,services:list<ServiceCard>} */
    public function __invoke(string $slug): array
    {
        $p = FreelanceProfile::query()->where('slug', $slug)->whereNotNull('published_at')->first() ?? throw new ServiceNotFound;
        $services = Service::query()->published()->with(['category', 'freelanceProfile'])->where('freelance_profile_id', $p->getKey())->orderByDesc('published_at')->get()
            ->map(fn (Service $s) => ServiceProjection::card($s))->all();

        return [
            'name' => $p->display_name, 'initials' => ServiceProjection::initials($p->display_name), 'headline' => $p->headline, 'city' => $p->city, 'bio' => $p->bio,
            'skills' => $p->skills ?? [], 'isDemo' => $p->is_demo, 'services' => $services,
        ];
    }
}
