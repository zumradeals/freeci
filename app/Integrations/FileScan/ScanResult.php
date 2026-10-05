<?php

namespace App\Integrations\FileScan;

enum ScanResult: string
{
    case Clean = 'clean';
    case Infected = 'infected';
    /** Service absent, injoignable ou en erreur : le fichier RESTE bloqué (jamais « propre par défaut »). */
    case Unavailable = 'unavailable';
}
