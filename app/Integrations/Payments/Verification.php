<?php

namespace App\Integrations\Payments;

final readonly class Verification
{
    public function __construct(
        public ProviderStatus $status,
        public ?int $amountXof = null,
        public ?string $currency = null,                   // null = non fourni par le prestataire (jamais supposé)
        public ?string $orderReference = null,
        public ?string $transactionReference = null,
        public ?string $environment = null,                // environnement déduit de la clé utilisée côté serveur, ou fourni par le prestataire
    ) {}
}
