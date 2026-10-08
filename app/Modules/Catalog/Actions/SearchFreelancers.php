<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Orders\Queries\ReviewQueries;
use App\Modules\Orders\Support\ReviewVisibility;
use App\Shared\Money;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Annuaire PUBLIC des freelances : profils publiés dont le compte n'est pas suspendu. Liste de champs explicite (jamais l'e-mail ni l'identifiant du compte).
 * Filtres : texte (nom, titre, compétences), catégorie d'un service publié, compétence, prix maximum (service publié le moins cher), délai maximum (service publié le plus rapide).
 * Tris : récents, nom, mieux notés (règle explicite : moyenne des avis publiés, services ET missions, puis nombre ; sans avis en dernier). Pagination stable (identifiant en dernier).
 */
final class SearchFreelancers
{
    public const SORTS = ['recents', 'nom', 'mieux-notes'];

    public const PER_PAGE = 12;

    public function __construct(private ReviewQueries $reviews, private FavoriteQueries $favorites) {}

    /** @return LengthAwarePaginator<int, array<string, mixed>> */
    public function __invoke(?string $q, ?string $category, ?string $skill, mixed $priceMax, mixed $delayMax, ?string $sort, int $page = 1, ?User $viewer = null): LengthAwarePaginator
    {
        $sort = in_array($sort, self::SORTS, true) ? $sort : 'recents';
        $int = fn (mixed $v, int $max) => is_numeric($v) && (int) $v > 0 && (int) $v <= $max ? (int) $v : null;
        $published = fn ($x) => $x->select(DB::raw(1))->from('services as s')->whereColumn('s.freelance_profile_id', 'p.id')->where('s.status', 'published')->whereNotNull('s.published_at')->where('s.published_at', '<=', now());

        $query = DB::table('freelance_profiles as p')->whereNotNull('p.published_at')->whereNotNull('p.slug')
            ->whereNotExists(fn ($x) => $x->select(DB::raw(1))->from('users')->whereColumn('users.id', 'p.user_id')->whereNotNull('users.suspended_at'))
            ->select('p.id', 'p.user_id', 'p.slug', 'p.display_name', 'p.headline', 'p.city', 'p.skills', 'p.is_demo')
            ->selectRaw("(select count(*) from services s where s.freelance_profile_id = p.id and s.status = 'published' and s.published_at is not null and s.published_at <= now()) as services_count")
            ->selectRaw("(select min(s.price_xof) from services s where s.freelance_profile_id = p.id and s.status = 'published' and s.published_at is not null and s.published_at <= now()) as min_price");

        $category = $category === '' ? null : $category;
        if ($category !== null) {
            $query->whereExists(fn ($x) => $published($x)->whereExists(fn ($c) => $c->select(DB::raw(1))->from('categories as c')->whereColumn('c.id', 's.category_id')->where('c.slug', $category)));
        }
        if (($max = $int($priceMax, 100_000_000)) !== null) {
            $query->whereExists(fn ($x) => $published($x)->where('s.price_xof', '<=', $max));
        }
        if (($d = $int($delayMax, 365)) !== null) {
            $query->whereExists(fn ($x) => $published($x)->where('s.delivery_days', '<=', $d));
        }
        $skill = $skill === null || trim($skill) === '' ? null : mb_substr(trim($skill), 0, 60);
        if ($skill !== null) {
            ListSkills::whereHasSkill($query, 'p.id', $skill);
        }
        $q = $q === null ? null : trim($q);
        foreach (array_slice(preg_split('/\s+/u', (string) $q, -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 6) as $term) {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
            $query->whereRaw("freeci_unaccent(concat_ws(' ', p.display_name, p.headline, p.skills::text)) ILIKE freeci_unaccent(?)", [$like]);
        }

        match ($sort) {
            'nom' => $query->orderByRaw('lower(freeci_unaccent(p.display_name)) asc'),
            'mieux-notes' => $query->leftJoinSub(ReviewVisibility::published(DB::table('reviews'))->selectRaw('subject_id, avg(rating) as r_avg, count(*) as r_count')->groupBy('subject_id'), 'rv', 'rv.subject_id', '=', 'p.user_id')
                ->orderByRaw('rv.r_avg DESC NULLS LAST')->orderByRaw('rv.r_count DESC NULLS LAST')->orderByDesc('p.published_at'),
            default => $query->orderByDesc('p.published_at'),
        };
        $query->orderBy('p.slug')->orderBy('p.id');

        $p = $query->paginate(self::PER_PAGE, ['*'], 'page', $page);
        $ids = $p->getCollection()->pluck('id')->all();
        $stats = $this->reviews->forProfiles($ids);
        $marked = $this->favorites->marked($viewer, 'freelance', $ids);

        return $p->through(fn ($r) => [
            'id' => $r->id, 'userId' => (string) $r->user_id, 'slug' => $r->slug, 'name' => $r->display_name, 'initials' => ServiceProjection::initials($r->display_name), 'headline' => $r->headline, 'city' => $r->city,
            'skills' => array_slice(json_decode($r->skills ?? '[]', true) ?: [], 0, 5), 'servicesCount' => (int) $r->services_count, 'minPrice' => $r->min_price !== null ? Money::xof((int) $r->min_price) : null, 'isDemo' => (bool) $r->is_demo,
            'ratingCount' => $stats[$r->id]['count'] ?? 0, 'ratingAvg' => $stats[$r->id]['avg'] ?? null, 'favorited' => isset($marked[$r->id]),
        ]);
    }
}
