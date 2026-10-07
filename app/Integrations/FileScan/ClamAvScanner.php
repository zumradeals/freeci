<?php

namespace App\Integrations\FileScan;

use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Exception\ExceptionInterface;
use Symfony\Component\Process\Process;

/**
 * Analyse par ClamAV (`clamdscan` : démon `clamd`, signatures chargées en mémoire). Présence NON présumée :
 * `isOperational()` vérifie l'exécutable et la réponse du démon ; sans cela le dépôt est désactivé.
 */
class ClamAvScanner implements FileScanner
{
    public function __construct(private string $binary) {}

    public function name(): string
    {
        return 'clamav';
    }

    public function isOperational(): bool
    {
        return Cache::remember('freeci.clamav.operational', 60, function () {
            if (! is_file($this->binary) || ! is_executable($this->binary)) {
                return false;
            }
            // Scan harmless content: --version alone does not prove the daemon can scan.
            $probe = tempnam(sys_get_temp_dir(), 'freeci-scan-');
            if ($probe === false) {
                return false;
            }
            try {
                if (file_put_contents($probe, "FreeCI scanner readiness check\n") === false) {
                    return false;
                }
                $p = new Process([$this->binary, '--no-summary', '--stream', $probe], timeout: 10);
                $p->run();

                return $p->isSuccessful();
            } catch (ExceptionInterface) {
                return false;
            } finally {
                @unlink($probe);
            }
        });
    }

    public function scan(string $absolutePath): ScanResult
    {
        if (! is_file($absolutePath) || ! $this->isOperational()) {
            return ScanResult::Unavailable;
        }
        // --stream : le contenu est envoyé au démon par la socket (pas de droits de lecture à accorder à clamd).
        $p = new Process([$this->binary, '--no-summary', '--stream', $absolutePath], timeout: 60);
        try {
            $p->run();
        } catch (ExceptionInterface) {
            return ScanResult::Unavailable;
        }

        return match ($p->getExitCode()) {
            0 => ScanResult::Clean,
            1 => ScanResult::Infected,
            default => ScanResult::Unavailable,   // 2 = erreur : jamais interprétée comme « propre »
        };
    }
}
