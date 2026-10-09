<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Data\ServiceCard;
use App\Modules\Catalog\Data\ServiceSearchCriteria;
use App\Modules\Catalog\Models\Service;
use App\Modules\Catalog\Support\Availability;
use App\Modules\Orders\Queries\ReviewQueries;
use App\Modules\Orders\Support\ReviewVisibility;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Recherche publique : uniquement des services publiés, filtres et tri validés au serveur, plein texte PostgreSQL (configuration française, accents ignorés) + préfixe de titre.
 * Pagination STABLE : chaque tri se termine par l'identifiant du service. Le tri « mieux notés » obéit à une règle explicite : moyenne des avis PUBLIÉS issus de ce service
 * (jamais ceux d'une mission), puis nombre d'avis ; les services sans avis passent en dernier. Aucun classement « recommandé », aucun badge.
 */
final class SearchServices
{
    public function __construct(private ReviewQueries $reviews, private FavoriteQueries $favorites) {}

    public function __invoke(ServiceSearchCriteria $c, int $page = 1, ?User $viewer = null): LengthAwarePaginator
    {
        $q = Service::query()->published()->with(['category', 'freelanceProfile']);

        if ($c->categorySlug !== null) {
            $q->whereHas('category', fn ($cat) => $cat->where('slug', $c->categorySlug));
        }
        if ($c->priceMin !== null) {
            $q->where('services.price_xof', '>=', $c->priceMin);
        }
        if ($c->priceMax !== null) {
            $q->where('services.price_xof', '<=', $c->priceMax);
        }
        if ($c->delayMax !== null) {
            $q->where('services.delivery_days', '<=', $c->delayMax);
        }
        if ($c->skill !== null) {
            ListSkills::whereHasSkill($q, 'services.freelance_profile_id', $c->skill);
        }
        if ($c->query !== null) {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $c->query).'%';
            $q->where(function ($w) use ($c, $like) {
                $w->whereRaw("services.search_document @@ websearch_to_tsquery('french', freeci_unaccent(?))", [$c->query])
                    ->orWhereRaw('freeci_unaccent(services.title) ILIKE freeci_unaccent(?)', [$like]);
            });
        }

        // Les services dont le freelance est indisponible restent visibles mais passent APRÈS les disponibles (quel que soit le tri).
        $q->orderByRaw('(select case when '.Availability::unavailableSql('fp').' then 1 else 0 end from freelance_profiles fp where fp.id = services.freelance_profile_id) asc');

        match ($c->sort) {
            'prix-croissant' => $q->orderBy('services.price_xof'),
            'prix-decroissant' => $q->orderByDesc('services.price_xof'),
            'delai-court' => $q->orderBy('services.delivery_days')->orderBy('services.price_xof'),
            'mieux-notes' => $q->leftJoinSub(ReviewVisibility::published(DB::table('reviews'))->where('origin', 'service')->selectRaw('service_id, avg(rating) as r_avg, count(*) as r_count')->groupBy('service_id'), 'rv', 'rv.service_id', '=', 'services.id')
                ->orderByRaw('rv.r_avg DESC NULLS LAST')->orderByRaw('rv.r_count DESC NULLS LAST')->orderByDesc('services.published_at'),
            'recents' => $q->orderByDesc('services.published_at'),
            default => $c->query !== null
                ? $q->orderByRaw("ts_rank(services.search_document, websearch_to_tsquery('french', freeci_unaccent(?))) DESC", [$c->query])->orderByDesc('services.published_at')
                : $q->orderByDesc('services.published_at'),
        };
        $q->orderBy('services.slug')->orderBy('services.id');

        $p = $q->paginate(ServiceSearchCriteria::PER_PAGE, ['services.*'], 'page', $page);
        $ids = $p->getCollection()->map(fn (Service $s) => (string) $s->getKey())->all();
        app(SellerSignals::class)->preload($p->getCollection()->map(fn (Service $s) => (string) $s->freelanceProfile->user_id)->all());
        $stats = $this->reviews->forServices($ids);
        $marked = $this->favorites->marked($viewer, 'service', $ids);

        return $p->through(fn (Service $s): ServiceCard => ServiceProjection::card($s)->withExtras($stats[(string) $s->getKey()] ?? null, isset($marked[(string) $s->getKey()])));
    }
}
