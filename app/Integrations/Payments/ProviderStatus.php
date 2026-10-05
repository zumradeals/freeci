<?php

namespace App\Integrations\Payments;

/** Statut vu par le prestataire (jamais celui de FreeCI). */
enum ProviderStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Indeterminate = 'indeterminate';
    case NotFound = 'not_found';
}
