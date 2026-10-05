<?php

namespace App\Console\Commands;

use App\Modules\Catalog\Models\ServiceVersion;

class ModerationQueue extends ModerationCommands
{
    protected $signature = 'freeci:moderation:queue {--show= : identifiant de version à afficher en détail}';

    protected $description = 'Liste les versions de service en attente de modération (lecture seule), ou en détaille une.';

    public function handle(): int
    {
        if ($id = $this->option('show')) {
            $v = ServiceVersion::query()->find($id);
            if ($v === null) {
                $this->error('Version introuvable.');

                return self::FAILURE;
            }
            $this->describe($v);

            return self::SUCCESS;
        }
        $rows = ServiceVersion::query()->where('state', 'in_review')->orderBy('submitted_at')->with('service.freelanceProfile')->get();
        $this->table(['Version', 'Service', 'v', 'Titre', 'Auteur', 'Soumise le'], $rows->map(fn ($v) => [$v->id, $v->service_id, $v->number, mb_substr($v->title, 0, 40), $v->service->freelanceProfile->display_name, $v->submitted_at?->format('Y-m-d H:i')])->all());
        $this->info($rows->count().' version(s) en attente.');

        return self::SUCCESS;
    }
}
