<?php

namespace Tests\Support;

use App\Integrations\FileScan\FileScanner;
use App\Integrations\FileScan\ScanResult;

/** Analyseur de test : « infecté » si le contenu porte le marqueur EICAR, « indisponible » si on le demande. */
class FakeScanner implements FileScanner
{
    public static bool $operational = true;

    public static bool $unavailable = false;

    public function isOperational(): bool
    {
        return self::$operational;
    }

    public function name(): string
    {
        return 'fake';
    }

    public function scan(string $absolutePath): ScanResult
    {
        if (self::$unavailable) {
            return ScanResult::Unavailable;
        }

        return str_contains((string) file_get_contents($absolutePath), 'EICAR-STANDARD-ANTIVIRUS-TEST-FILE') ? ScanResult::Infected : ScanResult::Clean;
    }
}
