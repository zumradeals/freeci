<?php

namespace App\Integrations\Payments;

final readonly class CheckoutRequest
{
    public function __construct(
        public string $paymentId,
        public string $providerReference,   // générée et enregistrée AVANT l'appel (docs/02 §8.3) ; stable pour la tentative
        public string $orderReference,
        public int $amountXof,
        public string $currency,
        public ?string $description = null,
        public ?string $successUrl = null,  // retour navigateur : informatif, il ne confirme jamais un paiement
        public ?string $errorUrl = null,
    ) {}
}
