<?php

namespace App\Modules\Orders\Data;

use App\Shared\Money;
use Carbon\CarbonInterface;

/** Dossier commun d'une commande, vu par l'une de ses parties (jamais par un tiers). */
final readonly class OrderDossier
{
    /**
     * @param  list<array{label:string,answer:string}>  $briefItems
     * @param  list<array{title:string,when:string,from:?string,to:?string,toTone:?string,note:?string,now:bool}>  $events
     * @param  list<array{kind:string,label:string,primary:bool}>  $actions
     */
    public function __construct(
        public string $reference,
        public int $version,
        public string $perspective,            // client | freelancer
        public string $stateValue,
        public string $stateLabel,
        public string $tone,
        public string $icon,
        public bool $isDemo,
        public string $title,
        public string $categoryName,
        public string $otherPartyLabel,        // « Freelance » ou « Client »
        public string $otherPartyName,
        public string $clientName,
        public string $freelancerName,
        public Money $price,
        public int $deliveryDays,
        public int $revisionsIncluded,
        public string $scope,
        public array $deliverables,
        public array $exclusions,
        public int $serviceVersion,
        public string $conditionsVersion,
        public CarbonInterface $conditionsAcceptedAt,
        public CarbonInterface $requestedAt,
        public CarbonInterface $responseDeadline,
        public ?CarbonInterface $acceptedAt,
        public ?CarbonInterface $paymentDeadline,
        public ?string $closureReason,
        public ?string $closureNote,
        public array $briefItems,
        public ?string $briefNotes,
        public bool $briefComplete,
        public int $briefMissing,
        public array $events,
        public array $actions,
        public int $stepIndex,                 // 0..6 sur 7 étapes
        public bool $isFinal,
    ) {}
}
