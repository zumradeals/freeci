<?php

namespace App\Modules\Admin\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Models\Service;
use Database\Seeders\DemoCatalogSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Installation VOLONTAIRE du jeu de démonstration (huit cartes de l'accueil), depuis l'administration ou la console. Aucune variable d'environnement
 * n'est nécessaire : le geste explicite (phrase, identité reconfirmée, journal d'audit) remplace ce contrôle. Les catégories existantes ne sont pas modifiées,
 * aucun compte de connexion n'est créé. Le compte rendu dit ce que le visiteur voit réellement.
 */
final class InstallDemoData
{
    public function __construct(private AdminAudit $audit) {}

    /** @return array{demo_published: int, home_visible: int, demo_total: int} */
    public function byAdmin(User $admin): array
    {
        return $this->audit->run($admin, 'demo.install', 'demo', null, 'Données de démonstration', null, fn () => $this->run());
    }

    /** @return array{demo_published: int, home_visible: int, demo_total: int} */
    public function run(): array
    {
        (new DemoCatalogSeeder)->seed(false);

        return [
            'demo_published' => Service::query()->published()->where('is_demo', true)->count(),
            'home_visible' => min(8, Service::query()->published()->count()),
            'demo_total' => DB::table('services')->where('is_demo', true)->count(),
        ];
    }
}
