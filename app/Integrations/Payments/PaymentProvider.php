<?php

namespace App\Integrations\Payments;

/**
 * Port `PaymentProvider` (docs/02 §7). Unique adaptateur : Genius Pay, lié à un environnement (sandbox | live).
 * Les adaptateurs n'appellent jamais d'Action ; les notifications entrent par un contrôleur dédié.
 */
interface PaymentProvider
{
    public function name(): string;

    /** sandbox | live — conservé pour chaque tentative ; jamais déduit après coup. */
    public function environment(): string;

    public function createCheckout(CheckoutRequest $request): CheckoutResult;

    /** Interroge le prestataire sur une référence : seule source de vérité de la confirmation. */
    public function verify(string $reference): Verification;

    /** @throws InvalidProviderEvent signature absente ou invalide, corps illisible, horodatage hors tolérance */
    public function parseEvent(string $rawBody, array $headers): ProviderEvent;
}
