<?php

namespace App\Console\Commands;

use App\Modules\Catalog\Models\ServiceVersion;
use App\Modules\Catalog\Moderation\ServiceModeration;

class ModerationApprove extends ModerationCommands
{
    protected $signature = 'freeci:moderation:approve {version : identifiant de la version} {--by= : courriel de l\'administrateur} {--yes : sans confirmation}';

    protected $description = 'Approuve une version de service en contrôle : elle devient la version publiée (le public la voit).';

    public function handle(ServiceModeration $moderation): int
    {
        if (($admin = $this->moderator()) === null) {
            return self::FAILURE;
        }
        $v = ServiceVersion::query()->find($this->argument('version'));
        if ($v === null) {
            $this->error('Version introuvable.');

            return self::FAILURE;
        }
        $this->describe($v);
        if (! $this->confirmed('Approuver et publier cette version ?')) {
            $this->warn('Abandon : rien n\'a été modifié.');

            return self::FAILURE;
        }

        return $this->run1(fn () => $moderation->approve($admin, $v->getKey()), 'Version approuvée et publiée. Les accords des commandes existantes ne sont pas modifiés.');
    }
}
