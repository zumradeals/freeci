<?php

namespace App\Modules\Missions\Support;

use App\Modules\Accounts\Models\User;
use App\Modules\Missions\Models\MissionEvent;

final class MissionHistory
{
    public static function log(string $missionId, string $type, ?User $actor, string $label, ?string $note = null, ?array $meta = null, ?string $versionId = null, ?string $proposalId = null): void
    {
        MissionEvent::create(['mission_id' => $missionId, 'version_id' => $versionId, 'proposal_id' => $proposalId, 'type' => $type, 'actor_id' => $actor?->getKey(), 'actor_label' => $label, 'note' => $note, 'meta' => $meta]);
    }
}
