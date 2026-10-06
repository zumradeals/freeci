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
    /** Remboursé CHEZ le prestataire : enregistré et signalé, jamais déduit ni exécuté par FreeCI. */
    case Refunded = 'refunded';
    /** Événement authentique mais sans effet sur l'état d'une tentative (type inconnu ou informatif). */
    case Other = 'other';
}
