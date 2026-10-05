<?php

namespace App\Modules\Finance\Enums;

/** États d'une TENTATIVE de paiement (docs/02 §4.4). Distincts des états de commande et de l'état financier. */
enum PaymentState: string
{
    case Created = 'created';
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Failed = 'failed';
    case Expired = 'expired';
    case Unknown = 'unknown';

    /** Tentative « ouverte » : tant qu'elle l'est, aucune nouvelle tentative n'est permise (anti-paiement aveugle). */
    public function isOpen(): bool
    {
        return in_array($this, [self::Created, self::Pending, self::Unknown], true);
    }

    /** Transitions non régressives : un état final (confirmé, échoué, expiré) ne change plus. */
    public function canTransitionTo(self $to): bool
    {
        return match ($this) {
            self::Created => in_array($to, [self::Pending, self::Failed, self::Expired, self::Confirmed], true),
            self::Pending => in_array($to, [self::Confirmed, self::Failed, self::Unknown, self::Expired], true),
            self::Unknown => in_array($to, [self::Confirmed, self::Failed, self::Expired], true),
            default => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Created, self::Pending => 'Vérification en cours',
            self::Unknown => 'Vérification en cours',
            self::Confirmed => 'Confirmé',
            self::Failed => 'Non abouti',
            self::Expired => 'Expiré',
        };
    }

    /** @return array{0: string, 1: string} ton et icône */
    public function tone(): array
    {
        return match ($this) {
            self::Confirmed => ['success', 'check-circle'],
            self::Failed => ['error', 'error'],
            self::Expired => ['neutral', 'minus-circle'],
            default => ['warning', 'clock'],
        };
    }
}
