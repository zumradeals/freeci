<?php

namespace App\Integrations\FileScan;

/** Port `FileScanner` (docs/02 §7) : analyse hors requête ; « indisponible » ⇒ le fichier reste bloqué. */
interface FileScanner
{
    /** Le service d'analyse est-il présent ET joignable ? Sans cela, aucun dépôt de fichier n'est accepté. */
    public function isOperational(): bool;

    public function scan(string $absolutePath): ScanResult;

    public function name(): string;
}
