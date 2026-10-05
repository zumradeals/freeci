<?php

namespace App\Modules\Catalog\Data;

use App\Shared\Money;

/** Projection publique d'un service pour les listes : liste de champs explicite (docs/02 §4.3). */
final readonly class ServiceCard
{
    public function __construct(
        public string $slug,
        public string $title,
        public string $categoryName,
        public string $sellerName,
        public string $sellerInitials,
        public string $sellerHeadline,
        public int $deliveryDays,
        public Money $price,
        public ?string $imageSrc,
        public ?string $imageAlt,
        public bool $isDemo,
    ) {}
}
