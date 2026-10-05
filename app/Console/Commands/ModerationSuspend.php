<?php

namespace App\Console\Commands;

use App\Modules\Catalog\Moderation\ServiceModeration;

class ModerationSuspend extends ModerationCommands
{
    protected $signature = 'freeci:moderation:suspend {service : identifiant du service} {--by= : courriel de l\'administrateur} {--reason= : motif (10 à 1000 caractères)} {--yes : sans confirmation}';

    protected $description = 'Retire un service en ligne du catalogue (suspension motivée). Les commandes en cours et leurs accords ne sont pas modifiés.';

    public function handle(ServiceModeration $moderation): int
    {
        if (($admin = $this->moderator()) === null) {
            return self::FAILURE;
        }
        $this->line('Suspension du service '.$this->argument('service').'. Motif : '.($this->option('reason') ?: '(manquant)'));
        if (! $this->confirmed('Confirmer la suspension ?')) {
            $this->warn('Abandon : rien n\'a été modifié.');

            return self::FAILURE;
        }

        return $this->run1(fn () => $moderation->suspend($admin, (string) $this->argument('service'), (string) $this->option('reason')), 'Service suspendu. Les commandes existantes ne sont ni supprimées ni modifiées.');
    }
}
