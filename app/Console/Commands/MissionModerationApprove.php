<?php

namespace App\Console\Commands;

use App\Modules\Missions\Actions\MissionModeration;
use App\Modules\Missions\Models\MissionVersion;

class MissionModerationApprove extends ModerationCommands
{
    protected $signature = 'freeci:moderation:mission-approve {version : identifiant de la version} {--by= : courriel de l\'administrateur} {--yes : sans confirmation}';

    protected $description = 'Approuve une version de mission en contrôle : elle devient la version publiée (les propositions faites sur une version précédente devront être reconfirmées).';

    public function handle(MissionModeration $moderation): int
    {
        if (($admin = $this->moderator()) === null) {
            return self::FAILURE;
        }
        $v = MissionVersion::query()->with('mission.client', 'category')->find($this->argument('version'));
        if ($v === null) {
            $this->error('Version introuvable.');

            return self::FAILURE;
        }
        $this->describeMission($v);
        if (! $this->confirmed('Approuver et publier cette version de mission ?')) {
            $this->warn('Abandon : rien n\'a été modifié.');

            return self::FAILURE;
        }

        return $this->run1(fn () => $moderation->approve($admin, $v->getKey()), 'Mission approuvée et publiée.');
    }
}
