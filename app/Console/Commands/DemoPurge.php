<?php

namespace App\Console\Commands;

use App\Modules\Admin\Actions\PurgeDemoData;
use Illuminate\Console\Command;

/** Retire UNIQUEMENT les données marquées « démonstration » (is_demo = true). Jamais de donnée réelle ; ce qui est référencé par l'historique est conservé. */
class DemoPurge extends Command
{
    protected $signature = 'freeci:demo-purge {--dry-run : afficher ce qui serait concerné, sans rien supprimer} {--force : confirmer sans question (indispensable en production hors terminal)}';

    protected $description = 'Supprime les services, missions, profils et comptes de démonstration (is_demo = true), et eux seuls ; archive ce qui est référencé par une commande.';

    public function handle(PurgeDemoData $purge): int
    {
        $c = PurgeDemoData::counts();
        $this->table(['Élément de démonstration', 'Nombre'], [['services', $c['services']], ['missions', $c['missions']], ['profils', $c['profiles']], ['comptes', $c['users']], ['commandes (conservées : historique)', $c['orders']]]);

        if (array_sum([$c['services'], $c['missions'], $c['profiles'], $c['users']]) === 0) {
            $this->info('Rien à supprimer.');

            return self::SUCCESS;
        }
        if ($this->option('dry-run')) {
            $this->line('Simulation : rien n\'a été supprimé. Faites une sauvegarde, puis relancez sans --dry-run.');

            return self::SUCCESS;
        }
        if (! $this->option('force') && ! $this->confirm('Supprimer définitivement ces données de démonstration ? (faites une sauvegarde avant)')) {
            $this->warn('Annulé.');

            return self::FAILURE;
        }

        $r = $purge->run();
        $this->table(['Résultat', 'Supprimés', 'Conservés (référencés par l\'historique)'], [
            ['services', $r['services_removed'], $r['services_kept']], ['missions', $r['missions_removed'], $r['missions_kept']],
            ['profils', $r['profiles_removed'], $r['profiles_kept']], ['comptes', $r['users_removed'], $r['users_kept']],
        ]);
        $this->info('Données de démonstration supprimées.');

        return self::SUCCESS;
    }
}
