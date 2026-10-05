<?php

namespace App\Modules\Catalog\Data;

/** Critères de recherche validés au serveur (docs/04 §2.4). */
final readonly class ServiceSearchCriteria
{
    public const SORTS = ['pertinence', 'prix-croissant', 'prix-decroissant', 'recents'];

    public const PER_PAGE = 12; // ≤ 20 (F11)

    public function __construct(
        public ?string $query = null,
        public ?string $categorySlug = null,
        public string $sort = 'pertinence',
    ) {}

    public static function make(?string $query, ?string $categorySlug, ?string $sort): self
    {
        $query = $query === null ? null : trim(preg_replace('/\s+/u', ' ', $query) ?? '');
        $query = ($query === '' || $query === null) ? null : mb_substr($query, 0, 100);
        $categorySlug = ($categorySlug === null || $categorySlug === '') ? null : mb_substr($categorySlug, 0, 80);
        $sort = in_array($sort, self::SORTS, true) ? $sort : 'pertinence';

        return new self($query, $categorySlug, $sort);
    }

    public function hasFilters(): bool
    {
        return $this->query !== null || $this->categorySlug !== null;
    }
}
