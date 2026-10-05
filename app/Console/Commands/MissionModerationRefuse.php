<?php

namespace App\Console\Commands;

use App\Modules\Missions\Actions\MissionModeration;
use App\Modules\Missions\Models\MissionVersion;

class MissionModerationRefuse extends ModerationCommands
{
    protected $signature = 'freeci:moderation:mission-refuse {version : identifiant de la version} {--by= : courriel de l\'administrateur} {--reason= : motif (10 à 1000 caractères, visible du client)} {--yes : sans confirmation}';

    protected $description = 'Refuse une version de mission en contrôle avec un motif obligatoire : le client la corrige et la soumet de nouveau.';

    public function handle(MissionModeration $moderation): int
    {
        if (($admin = $this->moderator()) === null) {
            return self::FAILURE;
        }
        $v = MissionVersion::query()->find($this->argument('version'));
        if ($v === null) {
            $this->error('Version introuvable.');

            return self::FAILURE;
        }
        $this->line("Refus de « {$v->title} » (v{$v->number}). Motif : ".($this->option('reason') ?: '(manquant)'));
        if (! $this->confirmed('Confirmer le refus ?')) {
            $this->warn('Abandon : rien n\'a été modifié.');

            return self::FAILURE;
        }

        return $this->run1(fn () => $moderation->requestChanges($admin, $v->getKey(), (string) $this->option('reason')), 'Correction demandée : le motif est visible du client. La version publiée éventuelle reste en ligne.');
    }
}
