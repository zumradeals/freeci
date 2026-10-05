<?php

namespace App\Modules\Files\Enums;

/** Cycle de vie d'une pièce jointe : seule « clean » est téléchargeable. */
enum FileState: string
{
    case Quarantined = 'quarantined';
    case Scanning = 'scanning';
    case Clean = 'clean';
    case Rejected = 'rejected';
    case Removed = 'removed';

    public function label(): string
    {
        return match ($this) {
            self::Quarantined, self::Scanning => 'Contrôle de sécurité en cours',
            self::Clean => 'Contrôle de sécurité réussi',
            self::Rejected => 'Refusé',
            self::Removed => 'Retiré',
        };
    }

    public function downloadable(): bool
    {
        return $this === self::Clean;
    }
}
