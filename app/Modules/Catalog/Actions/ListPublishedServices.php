<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Data\ServiceCard;
use App\Modules\Catalog\Models\Service;
use App\Modules\Orders\Queries\ReviewQueries;

/** Services récemment publiés (accueil). Aucun volume inventé : ce qui existe, rien de plus. */
final class ListPublishedServices
{
    /** @return list<ServiceCard> */
    public function __invoke(int $limit = 6): array
    {
        $services = Service::query()->published()
            ->with(['category', 'freelanceProfile'])
            ->orderByDesc('published_at')->orderBy('slug')->orderBy('id')
            ->limit(max(1, min($limit, 20)))
            ->get();
        $ids = $services->map(fn (Service $s) => (string) $s->getKey())->all();
        $stats = app(ReviewQueries::class)->forServices($ids);
        $marked = app(FavoriteQueries::class)->marked(auth()->user(), 'service', $ids);

        return $services->map(fn (Service $s) => ServiceProjection::card($s)->withExtras($stats[(string) $s->getKey()] ?? null, isset($marked[(string) $s->getKey()])))->all();
    }
}
