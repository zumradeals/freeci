<?php

namespace App\Modules\Catalog\Enums;

enum ServiceStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Published = 'published';
    case Suspended = 'suspended';
    case Archived = 'archived';

    /** Un service retiré après publication affiche « n'est plus disponible » (docs/04 §2.5). */
    public function isWithdrawn(): bool
    {
        return in_array($this, [self::Suspended, self::Archived], true);
    }
}
