<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Data\ServiceCard;
use App\Modules\Catalog\Data\ServiceSearchCriteria;
use App\Modules\Catalog\Models\Service;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Recherche publique : uniquement des services publiés, filtres et tri validés au serveur,
 * plein texte PostgreSQL (configuration française, accents ignorés) + préfixe de titre.
 */
final class SearchServices
{
    public function __invoke(ServiceSearchCriteria $c, int $page = 1): LengthAwarePaginator
    {
        $q = Service::query()->published()->with(['category', 'freelanceProfile']);

        if ($c->categorySlug !== null) {
            $q->whereHas('category', fn ($cat) => $cat->where('slug', $c->categorySlug));
        }

        if ($c->query !== null) {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $c->query).'%';
            $q->where(function ($w) use ($c, $like) {
                $w->whereRaw("services.search_document @@ websearch_to_tsquery('french', freeci_unaccent(?))", [$c->query])
                    ->orWhereRaw('freeci_unaccent(services.title) ILIKE freeci_unaccent(?)', [$like]);
            });
        }

        match ($c->sort) {
            'prix-croissant' => $q->orderBy('services.price_xof')->orderBy('services.slug'),
            'prix-decroissant' => $q->orderByDesc('services.price_xof')->orderBy('services.slug'),
            'recents' => $q->orderByDesc('services.published_at')->orderBy('services.slug'),
            default => $c->query !== null
                ? $q->orderByRaw("ts_rank(services.search_document, websearch_to_tsquery('french', freeci_unaccent(?))) DESC", [$c->query])
                    ->orderByDesc('services.published_at')->orderBy('services.slug')
                : $q->orderByDesc('services.published_at')->orderBy('services.slug'),
        };

        return $q->paginate(ServiceSearchCriteria::PER_PAGE, ['services.*'], 'page', $page)
            ->through(fn (Service $s): ServiceCard => ServiceProjection::card($s));
    }
}
