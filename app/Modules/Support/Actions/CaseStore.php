<?php

namespace App\Modules\Support\Actions;

use App\Modules\Notifications\Actions\Notify;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Écritures communes aux dossiers : création, messages, historique (ajout seul), notifications sans contenu privé. */
final class CaseStore
{
    public function __construct(private Notify $notify) {}

    /** @param array<string, mixed> $data */
    public function create(array $data): string
    {
        $id = (string) Str::uuid();
        $ref = 'SU-'.now()->format('ym').'-'.str_pad((string) DB::selectOne("select nextval('support_case_reference_seq') as n")->n, 5, '0', STR_PAD_LEFT);
        DB::table('support_cases')->insert($data + ['id' => $id, 'reference' => $ref, 'status' => 'open', 'opened_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    public function message(string $caseId, ?string $authorId, string $visibility, string $body, ?string $clientKey = null): int
    {
        return (int) DB::table('support_messages')->insertGetId(['case_id' => $caseId, 'author_id' => $authorId, 'visibility' => $visibility, 'body' => $body, 'client_key' => $clientKey, 'created_at' => now()]);
    }

    public function event(string $caseId, string $type, ?string $actorId, string $visibility = 'internal', ?string $note = null, ?array $meta = null): void
    {
        DB::table('support_events')->insert([
            'case_id' => $caseId, 'type' => $type, 'actor_id' => $actorId, 'visibility' => $visibility, 'note' => $note === null ? null : mb_substr($note, 0, 300),
            'meta' => $meta === null ? null : json_encode($meta), 'occurred_at' => now(),
        ]);
    }

    /** Notification (mécanisme du lot 7) : titre générique, jamais le contenu d'un message ni d'une pièce. */
    public function notify(string $userId, string $type, string $dedupe, string $title, string $reference): void
    {
        ($this->notify)($userId, $type, $dedupe, $title, "Dossier {$reference}", 'support.show', ['reference' => $reference]);
    }
}
