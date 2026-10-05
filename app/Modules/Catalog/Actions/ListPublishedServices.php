<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Data\ServiceCard;
use App\Modules\Catalog\Models\Service;

/** Services récemment publiés (accueil). Aucun volume inventé : ce qui existe, rien de plus. */
final class ListPublishedServices
{
    /** @return list<ServiceCard> */
    public function __invoke(int $limit = 6): array
    {
        return Service::query()->published()
            ->with(['category', 'freelanceProfile'])
            ->orderByDesc('published_at')->orderBy('slug')
            ->limit(max(1, min($limit, 20)))
            ->get()
            ->map(fn (Service $s) => ServiceProjection::card($s))
            ->all();
    }
}
