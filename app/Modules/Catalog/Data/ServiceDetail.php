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
        public int $version = 1,
        public bool $acceptsRequests = true,
        public string $sellerUserId = '',
        public ?string $sellerSlug = null,         // adresse du profil public, seulement si le profil est publié
        public string $id = '',                    // vide pour un aperçu non publié
        public int $ratingCount = 0,               // avis PUBLIÉS issus de ce service
        public ?string $ratingAvg = null,
        public bool $favorited = false,
    ) {}

    public function withExtras(?array $stats, bool $favorited): self
    {
        return new self($this->slug, $this->title, $this->summary, $this->scope, $this->categorySlug, $this->categoryName, $this->sellerName, $this->sellerInitials, $this->sellerHeadline, $this->sellerCity,
            $this->price, $this->deliveryDays, $this->revisionsIncluded, $this->deliverables, $this->exclusions, $this->clientInputs, $this->images, $this->isDemo, $this->version, $this->acceptsRequests,
            $this->sellerUserId, $this->sellerSlug, $this->id, (int) ($stats['count'] ?? 0), $stats['avg'] ?? null, $favorited);
    }
}
