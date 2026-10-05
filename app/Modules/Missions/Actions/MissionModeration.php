<?php

namespace App\Modules\Missions\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Exceptions\ModerationDenied;
use App\Modules\Missions\Exceptions\MissionConflict;
use App\Modules\Missions\Models\Mission;
use App\Modules\Missions\Models\MissionVersion;
use App\Modules\Missions\Models\Proposal;
use App\Modules\Missions\Support\MissionHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Modération des missions (même mécanisme que les services : administrateur en vigueur, jamais sa propre mission, motif, historique).
 * Appelée par les commandes console `freeci:moderation:mission-*` ; aucune interface web à ce stade.
 */
final class MissionModeration
{
    private const LABEL = 'modération (console)';

    public function approve(User $moderator, string $versionId): Mission
    {
        return DB::transaction(function () use ($moderator, $versionId) {
            [$m, $v] = $this->lockInReview($moderator, $versionId);
            if ($v->application_deadline->lte(now())) {
                throw new MissionConflict('La date limite de candidature est dépassée : l’auteur doit la corriger avant approbation.');
            }
            MissionVersion::query()->where('mission_id', $m->getKey())->where('state', 'published')->get()->each(fn (MissionVersion $o) => $o->forceFill(['state' => 'superseded'])->save());
            $now = now();
            $v->forceFill(['state' => 'published', 'decided_at' => $now, 'decided_by' => $moderator->getKey(), 'decision_note' => null, 'published_at' => $now])->save();
            $first = in_array($m->status, ['draft', 'in_review'], true);
            $m->published_version_id = $v->getKey();
            if ($first) {
                $m->status = 'open';
                $m->published_at = $now;
                $m->slug = $this->uniqueSlug($v->title);                      // adresse publique fixée à la première publication, puis stable
            }
            $m->row_version++;
            $m->save();
            $stale = Proposal::query()->where('mission_id', $m->getKey())->where('state', 'active')->count();
            MissionHistory::log($m->getKey(), 'approved', $moderator, self::LABEL, null, ['number' => $v->number, 'proposals_to_reconfirm' => $stale], $v->getKey());

            return $m;
        });
    }

    public function requestChanges(User $moderator, string $versionId, string $reason): Mission
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 1000) {
            throw new MissionConflict('Le motif est obligatoire (10 à 1000 caractères) : il est visible du client.');
        }

        return DB::transaction(function () use ($moderator, $versionId, $reason) {
            [$m, $v] = $this->lockInReview($moderator, $versionId);
            $v->forceFill(['state' => 'changes_requested', 'decided_at' => now(), 'decided_by' => $moderator->getKey(), 'decision_note' => $reason])->save();
            if ($m->status === 'in_review') {
                $m->forceFill(['status' => 'draft'])->save();
            }
            MissionHistory::log($m->getKey(), 'changes_requested', $moderator, self::LABEL, $reason, ['number' => $v->number], $v->getKey());

            return $m;
        });
    }

    /** @return array{0: Mission, 1: MissionVersion} */
    private function lockInReview(User $moderator, string $versionId): array
    {
        if (! $moderator->isAdministrator()) {
            throw new ModerationDenied('Habilitation administrateur en vigueur requise.');
        }
        $v = MissionVersion::query()->whereKey($versionId)->lockForUpdate()->first() ?? throw new MissionConflict('Version introuvable.');
        $m = Mission::query()->whereKey($v->mission_id)->lockForUpdate()->firstOrFail();
        if ($m->client_id === $moderator->getKey()) {
            throw new ModerationDenied('Vous ne pouvez pas modérer votre propre mission.');
        }
        if ($v->state !== 'in_review') {
            throw new MissionConflict('Cette version n’est plus en contrôle (déjà traitée ou retirée par son auteur).');
        }

        return [$m, $v];
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::limit(Str::slug($title) ?: 'mission', 120, '');
        $slug = $base;
        for ($i = 2; Mission::query()->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
