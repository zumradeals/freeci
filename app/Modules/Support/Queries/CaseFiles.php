<?php

namespace App\Modules\Support\Queries;

use App\Modules\Accounts\Models\User;
use App\Modules\Admin\Actions\AdminAudit;
use App\Modules\Files\Exceptions\FileForbidden;
use App\Modules\Support\Support\CaseRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Téléchargements : pièces d'un dossier (selon le canal du message) et, pour le personnel affecté, pièces de la commande du dossier. Même réponse pour tout refus ; consultations du personnel journalisées. */
final class CaseFiles
{
    public function __construct(private AdminAudit $audit) {}

    /** @return array{path: string, name: string} */
    public function forParty(User $u, string $fileId): array
    {
        $f = DB::table('file_assets')->where('id', $fileId)->whereNotNull('support_message_id')->first();
        $m = $f === null ? null : DB::table('support_messages')->where('id', $f->support_message_id)->first();
        $c = $m === null ? null : DB::table('support_cases')->where('id', $m->case_id)->first();
        $uid = $u->getKey();
        $ok = $c !== null && $f->state === 'clean' && (
            ($c->requester_id === $uid && in_array($m->visibility, ['requester', 'parties'], true))
            || ($c->counterparty_id === $uid && $m->visibility === 'parties' && in_array($c->kind, ['dispute', 'cancellation'], true))
        );

        return $ok ? $this->open($f) : throw new FileForbidden;
    }

    /** Personnel : pièce d'un message du dossier, ou pièce de la commande du dossier (litige, annulation, réclamation, suivi) — affecté, dossier ouvert, sans conflit. */
    public function forStaff(User $staff, string $reference, string $fileId): array
    {
        $c = DB::table('support_cases')->where('reference', $reference)->first();
        $f = DB::table('file_assets')->where('id', $fileId)->first();
        $uid = $staff->getKey();
        $ok = $c !== null && $f !== null && $f->state === 'clean' && $staff->isStaff() && $c->assignee_id === $uid && $c->access_opened_at !== null && ! CaseRules::involves($uid, $c)
            && in_array($c->status, CaseRules::LIVE, true) && (
                ($f->support_message_id !== null && DB::table('support_messages')->where('id', $f->support_message_id)->where('case_id', $c->id)->exists())
                || ($f->order_id !== null && $f->order_id === $c->order_id && ($c->kind === 'follow_up' || in_array($c->kind, CaseRules::DISPUTE_KINDS, true))
                    && ($f->delivery_id === null || DB::table('deliveries')->where('id', $f->delivery_id)->where('state', 'submitted')->exists()))
            );
        if (! $ok) {
            throw new FileForbidden;
        }
        $this->audit->record($staff, 'case.download', 'case', $reference, $f->original_name, null, 'done', $f->order_id !== null ? 'pièce de la commande' : 'pièce du dossier');

        return $this->open($f);
    }

    /** @return array{path: string, name: string} */
    private function open(object $f): array
    {
        if (! Storage::disk('private_files')->exists($f->storage_key)) {
            throw new FileForbidden;
        }

        return ['path' => Storage::disk('private_files')->path($f->storage_key), 'name' => $f->original_name];
    }
}
