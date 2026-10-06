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
        public string $id = '',
        public int $ratingCount = 0,                // avis PUBLIÉS issus de ce service (jamais d'une mission) ; 0 = aucune note affichée
        public ?string $ratingAvg = null,
        public bool $favorited = false,             // état pour l'utilisateur connecté seulement (favoris privés)
    ) {}

    /** Complète la carte avec des données réelles (avis publiés, favori de l'utilisateur) : rien n'est inventé quand elles manquent. */
    public function withExtras(?array $stats, bool $favorited): self
    {
        return new self($this->slug, $this->title, $this->categoryName, $this->sellerName, $this->sellerInitials, $this->sellerHeadline, $this->deliveryDays, $this->price, $this->imageSrc, $this->imageAlt,
            $this->isDemo, $this->id, (int) ($stats['count'] ?? 0), $stats['avg'] ?? null, $favorited);
    }
}
