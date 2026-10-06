<?php

namespace App\Integrations\Payments;

/** Choix du prestataire : celui des NOUVELLES tentatives (configuration) et celui d'une tentative existante (son prestataire enregistré, jamais réinterprété). */
final class PaymentProviders
{
    /** @return 'simulator'|'geniuspay_sandbox' */
    public function activeKey(): string
    {
        return GeniusPayConfig::sandboxSelected() ? GeniusPayConfig::SANDBOX : 'simulator';
    }

    public function active(): PaymentProvider
    {
        return $this->activeKey() === GeniusPayConfig::SANDBOX ? app(GeniusPayProvider::class) : app(SandboxPaymentProvider::class);
    }

    public function named(string $provider): PaymentProvider
    {
        return $provider === GeniusPayProvider::NAME ? app(GeniusPayProvider::class) : app(SandboxPaymentProvider::class);
    }
}
