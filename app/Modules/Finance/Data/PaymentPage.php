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
        public bool $paymentsOpen,           // nouveaux paiements possibles pour CETTE commande (mode, configuration, environnement de la commande)
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
        public string $environment = 'sandbox',            // sandbox | live : celui de la tentative, ou celui de la commande si aucune tentative
        public ?string $checkoutUrl = null,                // checkout hébergé de la tentative en attente (hôte contrôlé)
        public string $orderEnvironment = 'legacy',        // test | live | legacy (commande antérieure à l'environnement explicite)
        public ?string $unavailableMessage = null,         // raison compréhensible quand les nouveaux paiements ne sont pas ouverts
    ) {}
}
