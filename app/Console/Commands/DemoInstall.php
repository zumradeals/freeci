<?php

namespace App\Console\Commands;

use App\Modules\Admin\Actions\InstallDemoData;
use Illuminate\Console\Command;

class DemoInstall extends Command
{
    protected $signature = 'freeci:demo-install {--yes : ne pas demander de confirmation}';

    protected $description = 'Installe volontairement le jeu de démonstration (huit cartes sur l\'accueil), sans variable d\'environnement. Se retire avec freeci:demo-purge.';

    public function handle(InstallDemoData $install): int
    {
        if (! $this->option('yes') && ! $this->confirm('Installer les données de démonstration (services fictifs publiés sur l\'accueil) ?')) {
            $this->warn('Annulé.');

            return self::FAILURE;
        }
        $r = $install->run();
        $this->info("{$r['demo_published']} service(s) de démonstration publié(s) ({$r['demo_total']} au total). L'accueil affiche {$r['home_visible']} carte(s).");

        return $r['home_visible'] >= 8 ? self::SUCCESS : self::FAILURE;
    }
}
