<?php

namespace App\Console\Commands;

use App\Integrations\FileScan\FileScanner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class FilesCheck extends Command
{
    protected $signature = 'freeci:files:check';

    protected $description = 'Contrôle la chaîne des pièces jointes : disque privé, service d\'analyse, limites PHP et serveur web.';

    public function handle(FileScanner $scanner): int
    {
        $ok = true;
        $disk = Storage::disk('private_files');
        $probe = '.check-'.bin2hex(random_bytes(4));
        try {
            $disk->put($probe, 'x');
            $disk->delete($probe);
            $this->line('Disque privé : inscriptible ('.$disk->path('').')');
        } catch (\Throwable) {
            $this->error('Disque privé : NON inscriptible');
            $ok = false;
        }
        $this->line('Service de contrôle : '.$scanner->name().' — '.($scanner->isOperational() ? 'opérationnel' : 'INDISPONIBLE : le dépôt de fichiers est désactivé'));
        $this->line(sprintf('Limites applicatives : %d Mo par fichier, %d fichiers, %d Mo au total', config('freeci.files.max_mb'), config('freeci.files.max_files'), config('freeci.files.max_total_mb')));
        $toBytes = fn (string $v) => (int) $v * (str_ends_with(strtoupper($v), 'G') ? 1073741824 : (str_ends_with(strtoupper($v), 'M') ? 1048576 : (str_ends_with(strtoupper($v), 'K') ? 1024 : 1)));
        $upload = $toBytes((string) ini_get('upload_max_filesize'));
        $post = $toBytes((string) ini_get('post_max_size'));
        $need = (int) config('freeci.files.max_mb') * 1048576;
        $this->line('PHP : upload_max_filesize='.ini_get('upload_max_filesize').', post_max_size='.ini_get('post_max_size'));
        if ($upload < $need || $post < $need) {
            $this->warn('PHP refusera des fichiers inférieurs à la limite applicative : relevez upload_max_filesize et post_max_size (pool PHP-FPM), ainsi que client_max_body_size (nginx).');
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
