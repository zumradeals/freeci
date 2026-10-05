<?php

namespace App\Modules\Orders\Enums;

/** États de la commande (docs/02 §4.4). Séparés des états de paiement, de remboursement et de reversement. */
enum OrderState: string
{
    case AwaitingAcceptance = 'awaiting_acceptance';
    case AwaitingPayment = 'awaiting_payment';
    case AwaitingBrief = 'awaiting_brief';
    case InProgress = 'in_progress';
    case Delivered = 'delivered';
    case RevisionRequested = 'revision_requested';
    case Validated = 'validated';
    case Closed = 'closed';
    case Disputed = 'disputed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    /**
     * Transitions autorisées. Les passages à « en attente du brief » / « en cours » ne sont atteignables QUE par
     * `Orders\StartOrderIfReady`, appelée par `Finance\ConfirmPayment` (paiement vérifié côté serveur) ou à la complétion
     * du brief : aucune action utilisateur ne les déclenche. La base impose en plus un départ enregistré pour tout état de travail.
     *
     * @return list<self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::AwaitingAcceptance => [self::AwaitingPayment, self::Cancelled, self::Expired],
            self::AwaitingPayment => [self::AwaitingBrief, self::InProgress, self::Cancelled, self::Expired],
            self::AwaitingBrief => [self::InProgress],
            self::InProgress, self::RevisionRequested => [self::Delivered],
            self::Delivered => [self::RevisionRequested, self::Validated],
            self::Validated => [self::Closed],
            default => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedNext(), true);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Cancelled, self::Expired, self::Closed], true);
    }

    /** Libellé selon le point de vue : le client attend la réponse, le freelance doit la donner. */
    public function label(bool $asFreelancer = false): string
    {
        return match ($this) {
            self::AwaitingAcceptance => $asFreelancer ? 'À accepter' : 'En attente de réponse',
            self::AwaitingPayment => 'En attente de paiement',
            self::AwaitingBrief => 'En attente du brief',
            self::InProgress => 'En cours',
            self::Delivered => 'Livrée',
            self::RevisionRequested => 'Correction demandée',
            self::Validated => 'Validée',
            self::Closed => 'Clôturée',
            self::Disputed => 'En litige',
            self::Cancelled => 'Annulée',
            self::Expired => 'Expirée',
        };
    }

    /** Ton et icône du badge (jamais l'orange, `03` §3). */
    public function tone(bool $asFreelancer = false): array
    {
        return match ($this) {
            self::AwaitingAcceptance => $asFreelancer ? ['warning', 'warn'] : ['info', 'clock'],
            self::AwaitingPayment => ['warning', 'warn'],
            self::Cancelled, self::Expired => ['neutral', 'minus-circle'],
            self::Delivered => $asFreelancer ? ['info', 'clock'] : ['warning', 'warn'],
            self::RevisionRequested => $asFreelancer ? ['warning', 'warn'] : ['info', 'clock'],
            self::Disputed => ['error', 'error'],
            self::Validated, self::Closed => ['success', 'check-circle'],
            default => ['info', 'info'],
        };
    }
}
