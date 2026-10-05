<?php

namespace App\Integrations\Payments;

final readonly class Verification
{
    public function __construct(
        public ProviderStatus $status,
        public ?int $amountXof = null,
        public ?string $currency = null,
        public ?string $orderReference = null,
    ) {}
}
