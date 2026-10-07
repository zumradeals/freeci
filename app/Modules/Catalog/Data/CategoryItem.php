<?php

namespace App\Modules\Catalog\Data;

/** Projection publique d'une catégorie. */
final readonly class CategoryItem
{
    public function __construct(
        public string $slug,
        public string $name,
        public string $icon,
        public int $services = 0,
        public bool $featured = false,
    ) {}
}
