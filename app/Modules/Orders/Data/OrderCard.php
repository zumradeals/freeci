<?php

namespace App\Modules\Orders\Data;

use App\Shared\Money;

/** Ligne de liste : projection bornée à ce que la partie peut voir. */
final readonly class OrderCard
{
    public function __construct(
        public string $reference,
        public string $title,
        public string $otherParty,
        public Money $amount,
        public string $stateLabel,
        public string $tone,
        public string $icon,
        public string $info,
        public bool $isDemo,
        public string $environment = 'legacy',      // test | live | legacy
    ) {}
}
