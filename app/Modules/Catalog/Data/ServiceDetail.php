<?php

namespace App\Modules\Catalog\Data;

use App\Shared\Money;

/** Projection publique de la fiche d'un service. Aucune donnée de compte (e-mail, identifiant). */
final readonly class ServiceDetail
{
    /**
     * @param  list<string>  $deliverables
     * @param  list<string>  $exclusions
     * @param  list<string>  $clientInputs
     * @param  list<array{src:string,alt:string,caption:string}>  $images
     */
    public function __construct(
        public string $slug,
        public string $title,
        public string $summary,
        public string $scope,
        public string $categorySlug,
        public string $categoryName,
        public string $sellerName,
        public string $sellerInitials,
        public string $sellerHeadline,
        public ?string $sellerCity,
        public Money $price,
        public int $deliveryDays,
        public int $revisionsIncluded,
        public array $deliverables,
        public array $exclusions,
        public array $clientInputs,
        public array $images,
        public bool $isDemo,
    ) {}
}
