<?php

namespace App\Modules\Catalog\Data;

/** Critères de recherche validés au serveur (docs/04 §2.4). Tout vient de l'URL ; toute valeur invalide est ignorée (jamais une erreur, jamais un filtre fantôme). */
final readonly class ServiceSearchCriteria
{
    public const SORTS = ['pertinence', 'prix-croissant', 'prix-decroissant', 'delai-court', 'recents', 'mieux-notes'];

    public const PER_PAGE = 8;  // ≤ 20 (F11)

    public function __construct(
        public ?string $query = null,
        public ?string $categorySlug = null,
        public string $sort = 'pertinence',
        public ?int $priceMin = null,
        public ?int $priceMax = null,
        public ?int $delayMax = null,
        public ?string $skill = null,
    ) {}

    public static function make(?string $query, ?string $categorySlug, ?string $sort, mixed $priceMin = null, mixed $priceMax = null, mixed $delayMax = null, ?string $skill = null): self
    {
        $query = $query === null ? null : trim(preg_replace('/\s+/u', ' ', $query) ?? '');
        $query = ($query === '' || $query === null) ? null : mb_substr($query, 0, 100);
        $categorySlug = ($categorySlug === null || $categorySlug === '') ? null : mb_substr($categorySlug, 0, 80);
        $sort = in_array($sort, self::SORTS, true) ? $sort : 'pertinence';
        $skill = $skill === null ? null : trim($skill);
        $skill = ($skill === null || $skill === '') ? null : mb_substr($skill, 0, 60);
        $int = fn (mixed $v, int $max) => is_numeric($v) && (int) $v > 0 && (int) $v <= $max ? (int) $v : null;
        $min = $int($priceMin, 100_000_000);
        $maxP = $int($priceMax, 100_000_000);
        if ($min !== null && $maxP !== null && $min > $maxP) {
            [$min, $maxP] = [$maxP, $min];                       // bornes inversées : corrigées plutôt que « aucun résultat » trompeur
        }

        return new self($query, $categorySlug, $sort, $min, $maxP, $int($delayMax, 365), $skill);
    }

    public function hasFilters(): bool
    {
        return $this->query !== null || $this->categorySlug !== null || $this->priceMin !== null || $this->priceMax !== null || $this->delayMax !== null || $this->skill !== null;
    }
}
