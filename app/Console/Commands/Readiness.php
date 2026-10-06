<?php

namespace App\Console\Commands;

use App\Modules\Admin\Queries\OperationsStatus;
use Illuminate\Console\Command;

class Readiness extends Command
{
    protected $signature = 'freeci:readiness';

    protected $description = 'Liste courte de préparation à l\'ouverture (informative, lecture seule : n\'active ni ne bloque rien ; aucun secret affiché).';

    public function handle(OperationsStatus $status): int
    {
        $d = $status();
        $this->table(['Point', 'État', 'Détail'], array_map(fn ($r) => [$r['label'], ['ok' => 'REMPLIE', 'todo' => 'À TRAITER', 'info' => 'INFO'][$r['state']], $r['detail']], $d['readiness']));
        $late = array_filter($d['tasks'], fn ($t) => $t['state'] !== 'ok');
        $this->line('Tâches planifiées non à jour : '.($late === [] ? 'aucune' : implode(', ', array_map(fn ($t) => $t['task'].' ('.$t['state'].')', $late))));

        return self::SUCCESS;
    }
}
