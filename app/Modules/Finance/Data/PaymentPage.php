<?php

namespace App\Modules\Finance\Data;

use App\Shared\Money;
use Carbon\CarbonInterface;

/** Lecture de la page de paiement : tout vient de la base (commande, accord, tentative), rien de la requête. */
final readonly class PaymentPage
{
    public function __construct(
        public string $reference,
        public string $title,
        public string $sellerName,
        public Money $amount,
        public int $deliveryDays,
        public int $revisionsIncluded,
        public string $orderState,
        public string $orderStateLabel,
        public bool $sandboxAllowed,
        public ?CarbonInterface $deadline,
        public ?string $paymentState,          // created|pending|confirmed|failed|expired|unknown|null
        public ?string $paymentLabel,
        public ?string $paymentTone,
        public ?string $paymentIcon,
        public ?string $providerReference,
        public ?CarbonInterface $paymentChangedAt,
        public ?CarbonInterface $lastCheckedAt,
        public bool $canPay,
        public bool $canRefresh,
        public bool $briefComplete,
        public int $briefMissing,
        public ?CarbonInterface $startedAt,
        public ?CarbonInterface $dueAt,
        public bool $isDemo,
    ) {}
}
