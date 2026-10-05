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
        public ?CarbonInterface $startedAt = null,
        public ?CarbonInterface $dueAt = null,
        /** @var array{label:string,tone:string,icon:string,state:string,reference:string,at:?CarbonInterface}|null */
        public ?array $payment = null,
        public bool $paymentOpen = false,             // une tentative est en cours ou incertaine : « Payer » absent
        public bool $canPay = false,                  // simulateur autorisé pour cette commande, client, état et tentative OK
        public int $confirmedXof = 0,                 // montant encaissé (simulé), lu dans le registre
        /** @var list<array{id:string,name:string,size:string,label:string,tone:string,icon:string,url:?string,canRemove:bool,note:?string}> */
        public array $files = [],
        public bool $uploadsEnabled = false,          // un service de contrôle de sécurité est disponible
        public bool $canUpload = false,
        public bool $briefRequiresFiles = false,
        public string $uploadLimits = '',
        /** Livraisons, corrections, reports, délai d'examen : voir Queries\DeliverySection. */
        public array $delivery = [],
        public string $origin = 'service',               // service | mission
        public ?int $proposalNumber = null,
        public ?string $missionId = null,
        public int $messageUnread = 0,
    ) {}
}
