<?php

namespace App\Modules\Support\Actions;

use App\Integrations\FileScan\FileScanner;
use App\Modules\Accounts\Models\User;
use App\Modules\Admin\Actions\AdminAudit;
use App\Modules\Files\Actions\UploadBriefFile;
use App\Modules\Files\Enums\FileState;
use App\Modules\Files\Exceptions\FileRejected;
use App\Modules\Files\Exceptions\FilesDisabled;
use App\Modules\Support\Exceptions\SupportConflict;
use App\Modules\Support\Exceptions\SupportForbidden;
use App\Modules\Support\Support\CaseRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Échanges d'un dossier. Trois canaux : « requester » (demandeur ↔ équipe), « parties » (les deux parties d'un litige ↔ équipe) et « internal »
 * (équipe seule). Un message ou une pièce n'est JAMAIS une décision, une validation ni une modification de l'accord. Pièces : même chaîne privée
 * (type réel, quarantaine, contrôle) que le brief ; téléchargeables seulement une fois contrôlées.
 */
final class CaseThread
{
    public function __construct(private CaseStore $store, private FileScanner $scanner, private UploadBriefFile $validator, private AdminAudit $audit) {}

    /** @return array{0: int, 1: bool} [identifiant du message, répétition] */
    public function post(User $author, string $reference, string $body, string $visibility, ?UploadedFile $file, string $clientKey): array
    {
        $body = trim($body);
        if (mb_strlen($body) < 2 || mb_strlen($body) > (int) config('freeci.support.body_max')) {
            throw ValidationException::withMessages(['body' => 'Votre message doit faire entre 2 et '.config('freeci.support.body_max').' caractères.']);
        }
        $uid = $author->getKey();
        $case = DB::table('support_cases')->where('reference', $reference)->first();
        if ($case === null) {
            throw new SupportForbidden;
        }
        $role = $this->role($author, $case);
        $disputeLike = in_array($case->kind, ['dispute', 'cancellation'], true);
        $allowed = match ($role) {
            'requester' => [$disputeLike ? 'parties' : 'requester'],
            'counterparty' => ['parties'],
            'staff' => $disputeLike ? ['requester', 'parties', 'internal'] : ['requester', 'internal'],
            default => throw new SupportForbidden,
        };
        if (! in_array($visibility, $allowed, true)) {
            throw new SupportConflict('Ce canal d’échange n’est pas disponible pour ce dossier.');
        }
        if ($role === 'staff') {
            $this->audit->assertStaff($author);
            if (CaseRules::involves($uid, $case)) {
                throw new SupportConflict('Vous êtes partie prenante de ce dossier : vous ne pouvez pas le traiter.');
            }
        } elseif (! in_array($case->status, CaseRules::LIVE, true)) {
            throw new SupportConflict('Ce dossier n’accepte plus de nouveaux échanges.');
        }
        if ($role !== 'staff' && DB::table('support_messages')->where('author_id', $uid)->where('created_at', '>=', now()->subHour())->count() >= (int) config('freeci.support.messages_per_hour')) {
            throw new SupportConflict('Trop d’envois en peu de temps : patientez avant de poursuivre.');
        }
        $fileMeta = null;
        if ($file !== null) {
            if (! $this->scanner->isOperational()) {
                throw new FilesDisabled;
            }
            if (! $file->isValid()) {
                throw new FileRejected('Le téléversement a échoué : réessayez.');
            }
            $fileMeta = $this->validator->validateFile($file, (int) config('freeci.files.max_mb'));
            if (DB::table('file_assets')->whereIn('support_message_id', DB::table('support_messages')->where('case_id', $case->id)->select('id'))->count() >= (int) config('freeci.support.max_files')) {
                throw new FileRejected('Nombre maximal de pièces atteint pour ce dossier.');
            }
        }

        $dup = DB::table('support_messages')->where('case_id', $case->id)->where('author_id', $uid)->where('client_key', $clientKey)->value('id');
        if ($dup !== null) {
            return [(int) $dup, true];                               // double envoi : le même message, une seule fois
        }

        $id = DB::transaction(function () use ($case, $author, $role, $visibility, $body, $clientKey, $file, $fileMeta) {
            $locked = DB::table('support_cases')->where('id', $case->id)->lockForUpdate()->first();
            $id = $this->store->message($locked->id, $author->getKey(), $visibility, $body, $clientKey);
            if ($file !== null) {
                [$name, $ext, $mime] = $fileMeta;
                $key = 'o/'.substr($fid = (string) Str::uuid(), 0, 2).'/'.$fid;
                Storage::disk('private_files')->putFileAs(dirname($key), $file, basename($key));
                DB::table('file_assets')->insert([
                    'id' => (string) Str::uuid(), 'order_id' => null, 'support_message_id' => $id, 'uploader_id' => $author->getKey(), 'original_name' => $name, 'extension' => $ext, 'detected_mime' => $mime,
                    'size_bytes' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getRealPath()), 'storage_key' => $key, 'state' => FileState::Quarantined->value,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            // Un échange du demandeur / de l'autre partie rouvre l'examen s'il attendait cette réponse.
            if ($role !== 'staff' && in_array($locked->status, ['awaiting_requester', 'awaiting_party'], true)
                && (($locked->status === 'awaiting_requester' && $role === 'requester') || ($locked->status === 'awaiting_party' && $role === 'counterparty'))) {
                DB::table('support_cases')->where('id', $locked->id)->update(['status' => 'in_review', 'row_version' => $locked->row_version + 1, 'updated_at' => now()]);
                $this->store->event($locked->id, 'status', $author->getKey(), 'requester', 'Réponse reçue : examen repris', ['to' => 'in_review']);
            }
            $this->store->event($locked->id, 'message', $author->getKey(), $visibility, null, ['message' => $id]);

            // Notifications : jamais le contenu. Les canaux internes ne notifient personne.
            $ref = $locked->reference;
            $to = match ($visibility) {
                'parties' => array_filter([$locked->requester_id, $locked->counterparty_id], fn ($u) => $u !== null && $u !== $author->getKey()),
                'requester' => $role === 'staff' && $locked->requester_id !== null ? [$locked->requester_id] : [],
                default => [],
            };
            foreach ($to as $u) {
                $this->store->notify($u, 'support_update', 'support_message:'.$id.':'.$u, 'Nouveau message sur votre dossier d’assistance', $ref);
            }

            return $id;
        });

        return [$id, false];
    }

    /** @return 'requester'|'counterparty'|'staff'|null */
    private function role(User $u, object $case): ?string
    {
        $uid = $u->getKey();
        if ($case->requester_id === $uid) {
            return 'requester';
        }
        if ($case->counterparty_id === $uid) {
            return 'counterparty';
        }
        if ($case->assignee_id === $uid && $u->isStaff()) {
            return 'staff';
        }

        return null;
    }
}
