<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Data\CategoryItem;
use App\Modules\Catalog\Models\Category;

/** Lecture publique : les catégories, dans l'ordre d'affichage. */
final class ListCategories
{
    /** @return list<CategoryItem> */
    public function __invoke(): array
    {
        return Category::query()->orderBy('position')->get()
            ->map(fn (Category $c) => new CategoryItem($c->slug, $c->name, $c->icon))
            ->all();
    }
}
