<?php

namespace App\Console\Commands;

use App\Modules\Missions\Models\MissionVersion;

class MissionModerationQueue extends ModerationCommands
{
    protected $signature = 'freeci:moderation:mission-queue {--show= : identifiant de version à afficher en détail}';

    protected $description = 'Liste les versions de mission en attente de modération (lecture seule), ou en détaille une.';

    public function handle(): int
    {
        if ($id = $this->option('show')) {
            $v = MissionVersion::query()->with('mission.client', 'category')->find($id);
            if ($v === null) {
                $this->error('Version introuvable.');

                return self::FAILURE;
            }
            $this->describeMission($v);

            return self::SUCCESS;
        }
        $rows = MissionVersion::query()->where('state', 'in_review')->orderBy('submitted_at')->with('mission.client')->get();
        $this->table(['Version', 'Mission', 'v', 'Titre', 'Client', 'Soumise le'], $rows->map(fn ($v) => [$v->id, $v->mission_id, $v->number, mb_substr($v->title, 0, 40), $v->mission->client->name, $v->submitted_at?->format('Y-m-d H:i')])->all());
        $this->info($rows->count().' version(s) de mission en attente.');

        return self::SUCCESS;
    }
}
