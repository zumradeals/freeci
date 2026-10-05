<?php

namespace App\Modules\Orders\Enums;

enum ClosureReason: string
{
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';
    case CancelledBeforePayment = 'cancelled_before_payment';
    case ExpiredAcceptance = 'expired_acceptance';
    case ExpiredPayment = 'expired_payment';

    public function label(): string
    {
        return match ($this) {
            self::Declined => 'Demande refusée par le freelance',
            self::Withdrawn => 'Demande retirée par le client',
            self::CancelledBeforePayment => 'Commande annulée avant paiement',
            self::ExpiredAcceptance => 'Délai de réponse dépassé',
            self::ExpiredPayment => 'Délai de paiement dépassé',
        };
    }
}
