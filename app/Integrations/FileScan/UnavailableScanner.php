<?php

namespace App\Integrations\FileScan;

/** Défaut sûr : aucun service d'analyse. Aucun fichier ne peut devenir téléchargeable. */
class UnavailableScanner implements FileScanner
{
    public function isOperational(): bool
    {
        return false;
    }

    public function scan(string $absolutePath): ScanResult
    {
        return ScanResult::Unavailable;
    }

    public function name(): string
    {
        return 'aucun';
    }
}
