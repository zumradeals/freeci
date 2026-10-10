<?php

namespace App\Shared;

use App\Modules\Admin\Legal\LegalDefaults;
use App\Modules\Admin\Legal\LegalPages;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Adresses du plan du site (F-16). Uniquement du public : services publiés, profils publiés, missions ouvertes, listes, catégories, pages adoptées.
 * Jamais de démonstration, de compte suspendu, de brouillon, d'espace privé ni de contenu expiré. 50 000 adresses au plus par fichier.
 */
final class SitemapQueries
{
    public const MAX = 50000;

    public const KINDS = ['pages', 'services', 'freelances', 'missions'];

    /** @return list<array{loc: string, lastmod: ?string}> */
    public function urls(string $kind): array
    {
        return match ($kind) {
            'pages' => $this->pages(),
            'services' => $this->services(),
            'freelances' => $this->freelances(),
            'missions' => $this->missions(),
            default => [],
        };
    }

    private function loc(string $path): string
    {
        return Seo::base().$path;
    }

    private function date(mixed $v): ?string
    {
        return $v === null ? null : Carbon::parse($v)->timezone('UTC')->format('Y-m-d');
    }

    /** @return list<array{loc: string, lastmod: ?string}> */
    private function pages(): array
    {
        $out = [['loc' => $this->loc('/'), 'lastmod' => null], ['loc' => Seo::url('services.index'), 'lastmod' => null], ['loc' => Seo::url('freelances.index'), 'lastmod' => null], ['loc' => Seo::url('missions.index'), 'lastmod' => null]];
        $cats = DB::table('categories')->whereNull('archived_at')->orderBy('position')->orderBy('id')->get(['id', 'slug']);
        $withServices = DB::table('services')->where('status', 'published')->whereNotNull('published_at')->where('is_demo', false)->distinct()->pluck('category_id')->flip();
        $withMissions = DB::table('missions as m')->join('mission_versions as v', 'v.id', '=', 'm.published_version_id')->where('m.status', 'open')->where('m.is_demo', false)->where('v.application_deadline', '>', now())->distinct()->pluck('v.category_id')->flip();
        foreach ($cats as $c) {
            if ($withServices->has($c->id)) {
                $out[] = ['loc' => Seo::url('services.index', ['categorie' => $c->slug]), 'lastmod' => null];
            }
            if ($withMissions->has($c->id)) {
                $out[] = ['loc' => Seo::url('missions.index', ['categorie' => $c->slug]), 'lastmod' => null];
            }
        }
        foreach (array_keys(LegalDefaults::PAGES) as $slug) {
            if (LegalPages::adopted($slug)) {
                $out[] = ['loc' => Seo::url('info', ['page' => $slug]), 'lastmod' => null];
            }
        }

        return $out;
    }

    /** @return list<array{loc: string, lastmod: ?string}> */
    private function services(): array
    {
        return DB::table('services')->join('freelance_profiles as p', 'p.id', '=', 'services.freelance_profile_id')->join('users as u', 'u.id', '=', 'p.user_id')
            ->where('services.status', 'published')->whereNotNull('services.published_at')->where('services.published_at', '<=', now())->where('services.is_demo', false)->where('p.is_demo', false)->whereNull('u.suspended_at')
            ->orderByDesc('services.updated_at')->orderBy('services.id')->limit(self::MAX)->get(['services.slug', 'services.updated_at'])
            ->map(fn ($r) => ['loc' => Seo::url('services.show', $r->slug), 'lastmod' => $this->date($r->updated_at)])->all();
    }

    /** @return list<array{loc: string, lastmod: ?string}> */
    private function freelances(): array
    {
        return DB::table('freelance_profiles as p')->join('users as u', 'u.id', '=', 'p.user_id')->whereNotNull('p.published_at')->where('p.is_demo', false)->whereNull('u.suspended_at')
            ->orderByDesc('p.updated_at')->orderBy('p.id')->limit(self::MAX)->get(['p.slug', 'p.updated_at'])
            ->map(fn ($r) => ['loc' => Seo::url('freelances.show', $r->slug), 'lastmod' => $this->date($r->updated_at)])->all();
    }

    /** @return list<array{loc: string, lastmod: ?string}> */
    private function missions(): array
    {
        return DB::table('missions as m')->join('mission_versions as v', 'v.id', '=', 'm.published_version_id')->join('users as u', 'u.id', '=', 'm.client_id')
            ->where('m.status', 'open')->where('m.is_demo', false)->where('v.application_deadline', '>', now())->whereNull('u.suspended_at')
            ->orderByDesc('m.updated_at')->orderBy('m.id')->limit(self::MAX)->get(['m.slug', 'm.updated_at'])
            ->map(fn ($r) => ['loc' => Seo::url('missions.show', $r->slug), 'lastmod' => $this->date($r->updated_at)])->all();
    }
}
