<?php

namespace App\Modules\Messaging\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Files\Enums\FileState;
use App\Modules\Files\Exceptions\FileForbidden;
use App\Modules\Files\Models\FileAsset;
use App\Modules\Messaging\Models\Conversation;
use App\Modules\Messaging\Models\Message;
use Illuminate\Support\Facades\Storage;

/** Téléchargement d'une pièce jointe de message : participants de la conversation, fichier CONTRÔLÉ uniquement. Même réponse pour tout refus. */
final class MessageFiles
{
    /** @return array{path: string, name: string} */
    public function open(User $u, string $fileId): array
    {
        $f = FileAsset::query()->whereKey($fileId)->whereNotNull('message_id')->first();
        $m = $f === null ? null : Message::query()->find($f->message_id);
        $c = $m === null ? null : Conversation::query()->find($m->conversation_id);
        if ($f === null || $c === null || ! $c->isParticipant($u) || $f->state !== FileState::Clean || ! Storage::disk('private_files')->exists($f->storage_key)) {
            throw new FileForbidden;
        }

        return ['path' => Storage::disk('private_files')->path($f->storage_key), 'name' => $f->original_name];
    }
}
