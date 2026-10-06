<?php

namespace App\Integrations\Payments;

final readonly class CheckoutResult
{
    public function __construct(
        public string $providerReference,                  // notre référence de tentative
        public ?string $redirectUrl = null,                // checkout hébergé (hôte contrôlé)
        public ?string $transactionReference = null,       // référence attribuée par le prestataire
        public ?string $transactionId = null,
        public ?string $expiresAt = null,
        public bool $bindingVerified = false,              // le prestataire a renvoyé NOTRE référence pour cette transaction
        public ?string $environment = null,
    ) {}
}
