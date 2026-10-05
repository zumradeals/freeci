<?php

namespace App\Console\Commands;

use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Models\FreelanceProfile;
use App\Modules\Catalog\Models\Service;
use App\Modules\Orders\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Retire UNIQUEMENT les données marquées « démonstration » (is_demo = true). Jamais de donnée réelle. */
class DemoPurge extends Command
{
    protected $signature = 'freeci:demo-purge {--force : confirmer sans question (indispensable en production hors terminal)}';

    protected $description = 'Supprime les services, profils et comptes de démonstration (is_demo = true), et eux seuls.';

    public function handle(): int
    {
        $counts = [
            'services' => Service::where('is_demo', true)->count(),
            'profils' => FreelanceProfile::where('is_demo', true)->count(),
            'comptes' => User::where('is_demo', true)->count(),
        ];
        $this->table(['Élément de démonstration', 'Nombre'], collect($counts)->map(fn ($n, $k) => [$k, $n])->values()->all());

        if (array_sum($counts) === 0) {
            $this->info('Rien à supprimer.');

            return self::SUCCESS;
        }
        if (! $this->option('force') && ! $this->confirm('Supprimer définitivement ces données de démonstration ? (faites une sauvegarde avant)')) {
            $this->warn('Annulé.');

            return self::FAILURE;
        }

        DB::transaction(function () {
            // Ce qui est référencé par une commande est CONSERVÉ (accord et historique sont en ajout seul).
            Service::where('is_demo', true)->whereNotIn('id', Order::whereNotNull('service_id')->select('service_id'))->delete();
            // Un profil de démonstration lié à un service restant est conservé.
            FreelanceProfile::where('is_demo', true)->whereDoesntHave('services')->delete();
            User::where('is_demo', true)->whereDoesntHave('freelanceProfile')
                ->whereNotIn('id', Order::select('client_id'))->whereNotIn('id', Order::select('freelancer_id'))
                ->whereNotIn('id', DB::table('missions')->select('client_id'))->whereNotIn('id', DB::table('proposals')->select('freelancer_id'))->delete();      // missions et propositions sont conservées avec leurs auteurs
        });

        $this->info('Données de démonstration supprimées.');

        return self::SUCCESS;
    }
}
