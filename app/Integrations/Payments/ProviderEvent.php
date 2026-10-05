<?php

namespace App\Integrations\Payments;

/** Notification AUTHENTIFIÉE d'un prestataire. Son contenu n'est jamais cru sur parole : le statut est revérifié. */
final readonly class ProviderEvent
{
    public function __construct(
        public string $provider,
        public string $eventId,
        public string $reference,
        public ProviderStatus $status,
        public array $payload,
    ) {}
}
