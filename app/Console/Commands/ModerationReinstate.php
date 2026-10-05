<?php

namespace App\Console\Commands;

use App\Modules\Catalog\Moderation\ServiceModeration;

class ModerationReinstate extends ModerationCommands
{
    protected $signature = 'freeci:moderation:reinstate {service : identifiant du service} {--by= : courriel de l\'administrateur} {--yes : sans confirmation}';

    protected $description = 'Remet en ligne un service suspendu par la modération (sa version publiée redevient visible).';

    public function handle(ServiceModeration $moderation): int
    {
        if (($admin = $this->moderator()) === null) {
            return self::FAILURE;
        }
        if (! $this->confirmed('Remettre ce service en ligne ?')) {
            $this->warn('Abandon : rien n\'a été modifié.');

            return self::FAILURE;
        }

        return $this->run1(fn () => $moderation->reinstate($admin, (string) $this->argument('service')), 'Service remis en ligne.');
    }
}
