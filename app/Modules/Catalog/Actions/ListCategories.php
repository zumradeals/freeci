<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Data\CategoryItem;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Service;

/** Lecture publique : les catégories, dans l'ordre d'affichage. */
final class ListCategories
{
    /** @return list<CategoryItem> */
    public function __invoke(): array
    {
        return Category::query()->active()->orderBy('position')->get()
            ->map(fn (Category $c) => new CategoryItem($c->slug, $c->name, $c->icon))
            ->all();
    }

    /**
     * Accueil : toutes les catégories proposées, même vides (le visiteur voit l'étendue de l'offre), avec le nombre de services publiés quand il y en a.
     *
     * @return list<CategoryItem>
     */
    public function withServiceCounts(): array
    {
        $counts = Service::query()->published()->selectRaw('category_id, count(*) as n')->groupBy('category_id')->pluck('n', 'category_id');

        return Category::query()->active()->orderBy('position')->get()
            ->map(fn (Category $c) => new CategoryItem($c->slug, $c->name, $c->icon, (int) ($counts[$c->getKey()] ?? 0), $c->featured_at !== null))
            ->all();
    }
}
