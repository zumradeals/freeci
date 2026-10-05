<?php

namespace App\Modules\Files\Actions;

use App\Integrations\FileScan\FileScanner;
use App\Modules\Accounts\Models\User;
use App\Modules\Files\Enums\FileState;
use App\Modules\Files\Exceptions\FileRejected;
use App\Modules\Files\Exceptions\FilesDisabled;
use App\Modules\Files\Models\FileAsset;
use App\Modules\Orders\Enums\OrderState;
use App\Modules\Orders\Exceptions\InvalidTransition;
use App\Modules\Orders\Exceptions\OrderForbidden;
use App\Modules\Orders\Models\Order;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * `Files\UploadPrivateFile` pour le brief : stockage privé à clé opaque, validation de la taille, de l'extension et du type RÉEL,
 * puis QUARANTAINE. Le fichier n'est téléchargeable qu'après un contrôle de sécurité réussi (ScanBriefFile).
 */
final class UploadBriefFile
{
    /** extension => types MIME réels acceptés. */
    public const ALLOWED = [
        'pdf' => ['application/pdf'],
        'png' => ['image/png'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'webp' => ['image/webp'],
        'dwg' => ['image/vnd.dwg', 'application/acad', 'application/x-acad', 'application/autocad_dwg', 'application/octet-stream'],
    ];

    private const DANGEROUS_SEGMENTS = ['php', 'phtml', 'phar', 'exe', 'dll', 'js', 'mjs', 'sh', 'bash', 'bat', 'cmd', 'com', 'scr', 'msi', 'html', 'htm', 'svg', 'jar', 'ps1', 'vbs', 'py', 'pl'];

    public function __construct(private FileScanner $scanner) {}

    /** @return string identifiant du fichier (état : quarantaine) */
    public function __invoke(User $client, string $reference, UploadedFile $file): string
    {
        $order = Order::query()->where('reference', $reference)->where('client_id', $client->getKey())->first();
        if ($order === null) {
            throw new OrderForbidden;
        }
        if (! $this->scanner->isOperational()) {
            throw new FilesDisabled;
        }
        if (! $file->isValid()) {
            throw new FileRejected('Le téléversement a échoué : réessayez.');
        }

        [$name, $ext, $mime] = $this->validate($file);

        return DB::transaction(function () use ($order, $client, $file, $name, $ext, $mime) {
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            if (! in_array($locked->state, [OrderState::AwaitingAcceptance, OrderState::AwaitingPayment, OrderState::AwaitingBrief], true) || $locked->started_at !== null) {
                throw new InvalidTransition;
            }
            $active = FileAsset::query()->where('order_id', $locked->getKey())->whereNotIn('state', [FileState::Rejected->value, FileState::Removed->value]);
            if ((clone $active)->count() >= (int) config('freeci.files.max_files')) {
                throw new FileRejected('Nombre maximal de fichiers atteint ('.config('freeci.files.max_files').').');
            }
            if ((clone $active)->sum('size_bytes') + $file->getSize() > (int) config('freeci.files.max_total_mb') * 1048576) {
                throw new FileRejected('Taille totale maximale atteinte ('.config('freeci.files.max_total_mb').' Mo).');
            }

            $key = 'o/'.substr($id = (string) Str::uuid(), 0, 2).'/'.$id;            // clé opaque : jamais le nom d'origine
            Storage::disk('private_files')->putFileAs(dirname($key), $file, basename($key));

            $asset = FileAsset::create([
                'order_id' => $locked->getKey(), 'uploader_id' => $client->getKey(), 'original_name' => $name, 'extension' => $ext,
                'detected_mime' => $mime, 'size_bytes' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getRealPath()),
                'storage_key' => $key, 'state' => FileState::Quarantined,
            ]);
            $locked->events()->create(['type' => 'brief_file_added', 'actor_id' => $client->getKey(), 'note' => $name]);

            return $asset->getKey();
        });
    }

    /** @return array{0: string, 1: string, 2: string} [nom assaini, extension, type réel] */
    private function validate(UploadedFile $file): array
    {
        $max = (int) config('freeci.files.max_mb') * 1048576;
        if ($file->getSize() === 0) {
            throw new FileRejected('Fichier vide.');
        }
        if ($file->getSize() > $max) {
            throw new FileRejected('Fichier trop volumineux (maximum '.config('freeci.files.max_mb').' Mo).');
        }

        $name = trim(preg_replace('/[\x00-\x1F\x7F\\\\\/]+/u', '', basename(str_replace('\\', '/', (string) $file->getClientOriginalName()))) ?? '');
        $name = mb_substr($name, -120);
        $parts = array_map('strtolower', explode('.', $name));
        $ext = count($parts) > 1 ? end($parts) : '';
        if (! array_key_exists($ext, self::ALLOWED)) {
            throw new FileRejected('Format non autorisé. Formats acceptés : '.implode(', ', array_keys(self::ALLOWED)).'.');
        }
        foreach (array_slice($parts, 1, -1) as $segment) {          // « plan.php.pdf », « a.exe.png »…
            if (in_array($segment, self::DANGEROUS_SEGMENTS, true)) {
                throw new FileRejected('Nom de fichier non autorisé.');
            }
        }

        $path = $file->getRealPath();
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';     // type RÉEL, pas celui déclaré par le navigateur
        $head = (string) file_get_contents($path, false, null, 0, 16);
        if (str_starts_with($head, 'MZ') || str_starts_with($head, "\x7FELF") || str_starts_with($head, '#!')) {
            throw new FileRejected('Contenu exécutable refusé.');
        }
        if ($ext === 'dwg' ? ! str_starts_with($head, 'AC10') : ! in_array($mime, self::ALLOWED[$ext], true)) {
            throw new FileRejected('Le contenu du fichier ne correspond pas à son format.');
        }
        if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true) && @getimagesize($path) === false) {
            throw new FileRejected('Image illisible.');
        }

        return [$name, $ext, $ext === 'dwg' ? 'image/vnd.dwg' : $mime];
    }
}
