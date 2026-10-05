<?php

namespace App\Integrations\Payments;

final readonly class CheckoutResult
{
    public function __construct(public string $providerReference, public ?string $redirectUrl = null) {}
}
