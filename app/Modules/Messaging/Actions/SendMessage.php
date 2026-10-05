<?php

namespace App\Modules\Messaging\Actions;

use App\Integrations\FileScan\FileScanner;
use App\Modules\Accounts\Models\User;
use App\Modules\Files\Actions\UploadBriefFile;
use App\Modules\Files\Enums\FileState;
use App\Modules\Files\Exceptions\FileRejected;
use App\Modules\Files\Models\FileAsset;
use App\Modules\Messaging\Exceptions\MessagingConflict;
use App\Modules\Messaging\Exceptions\MessagingForbidden;
use App\Modules\Messaging\Models\Conversation;
use App\Modules\Messaging\Models\Message;
use App\Modules\Notifications\Actions\Notify;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Envoi d'un message (texte et/ou UNE pièce jointe). Participants seulement ; une soumission = un message (clé unique) ; débit limité ;
 * pièce jointe : chaîne privée existante (type réel, quarantaine, contrôle) — non téléchargeable tant qu'elle n'est pas contrôlée.
 * Un message ne modifie jamais une commande : aucun état, accord, report ou livraison n'est touché ici.
 */
final class SendMessage
{
    public function __construct(private FileScanner $scanner, private UploadBriefFile $validator, private Notify $notify) {}

    /** @return array{0: Message, 1: bool, 2: ?string} [message, vrai si rejoué, identifiant du fichier à contrôler] */
    public function __invoke(User $sender, string $conversationId, string $body, ?UploadedFile $file, string $clientKey): array
    {
        $max = (int) config('freeci.messaging.body_max');
        $body = trim($body);
        if ($body === '' && $file === null) {
            throw ValidationException::withMessages(['body' => 'Écrivez un message ou joignez un fichier.']);
        }
        if (mb_strlen($body) > $max) {
            throw ValidationException::withMessages(['body' => "Un message fait au plus {$max} caractères."]);
        }
        $conv = Conversation::query()->whereKey($conversationId)->first();
        if ($conv === null || ! $conv->isParticipant($sender)) {
            throw new MessagingForbidden;
        }
        $meta = null;
        if ($file !== null) {
            if (! $this->scanner->isOperational()) {
                throw new MessagingConflict('Les pièces jointes sont désactivées sur cette installation (aucun service de contrôle de sécurité). Envoyez votre message sans fichier.');
            }
            if (! $file->isValid()) {
                throw ValidationException::withMessages(['file' => 'Le téléversement a échoué : réessayez.']);
            }
            try {
                $meta = $this->validator->validateFile($file, (int) config('freeci.files.max_mb'));
            } catch (FileRejected $e) {
                throw ValidationException::withMessages(['file' => $e->getMessage()]);
            }
        }

        return DB::transaction(function () use ($sender, $conv, $body, $file, $meta, $clientKey) {
            $locked = Conversation::query()->whereKey($conv->getKey())->lockForUpdate()->firstOrFail();
            $existing = Message::query()->where(['conversation_id' => $locked->getKey(), 'sender_id' => $sender->getKey(), 'client_key' => $clientKey])->first();
            if ($existing !== null) {
                return [$existing, true, null];                         // double soumission : le même message, une seule fois
            }
            if (! ConversationRules::canSend($locked)) {
                throw new MessagingConflict('Cette conversation n’accepte plus de nouveaux messages.');
            }
            $recent = Message::query()->where('sender_id', $sender->getKey())->where('created_at', '>', now()->subMinutes(10))->count();
            if ($recent >= (int) config('freeci.messaging.per_10_minutes')) {
                throw new MessagingConflict('Vous avez envoyé beaucoup de messages en peu de temps : patientez quelques minutes.');
            }

            $m = Message::create(['conversation_id' => $locked->getKey(), 'sender_id' => $sender->getKey(), 'body' => $body, 'client_key' => $clientKey]);
            $fileId = null;
            if ($file !== null) {
                [$name, $ext, $mime] = $meta;
                $key = 'o/'.substr($id = (string) Str::uuid(), 0, 2).'/'.$id;
                Storage::disk('private_files')->putFileAs(dirname($key), $file, basename($key));
                $fileId = FileAsset::create([
                    'order_id' => null, 'message_id' => $m->getKey(), 'uploader_id' => $sender->getKey(), 'original_name' => $name, 'extension' => $ext, 'detected_mime' => $mime,
                    'size_bytes' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getRealPath()), 'storage_key' => $key, 'state' => FileState::Quarantined,
                ])->getKey();
            }
            $locked->forceFill(['last_message_id' => $m->getKey(), 'last_message_at' => $m->created_at ?? now(), $locked->readColumn($sender) => $m->getKey(), 'updated_at' => now()])->save();
            $this->notify->message($locked->counterpartId($sender), $locked->getKey(), $m->getKey(), $sender->name);

            return [$m, false, $fileId];
        });
    }
}
