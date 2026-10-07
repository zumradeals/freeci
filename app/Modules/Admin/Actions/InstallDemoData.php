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

    /**
     * Pourquoi les services de démonstration ne s'affichent pas : décompte par cause. « visibles » = ce que le visiteur voit réellement (même règle que le catalogue).
     *
     * @return array{total: int, visible: int, archived: int, other_status: int, vendor_suspended: int, future: int, home: int}
     */
    public static function diagnostic(): array
    {
        $demo = fn () => Service::query()->where('is_demo', true);
        $suspendedVendor = fn ($q) => $q->whereExists(fn ($x) => $x->select(DB::raw(1))->from('freelance_profiles')->join('users', 'users.id', '=', 'freelance_profiles.user_id')
            ->whereColumn('freelance_profiles.id', 'services.freelance_profile_id')->whereNotNull('users.suspended_at'));

        return [
            'total' => $demo()->count(),
            'visible' => $demo()->published()->count(),
            'archived' => $demo()->where('status', 'archived')->count(),
            'other_status' => $demo()->whereNotIn('status', ['published', 'archived'])->count(),
            'vendor_suspended' => $suspendedVendor($demo()->where('status', 'published'))->count(),
            'future' => $demo()->where('status', 'published')->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '>', now()))->count(),
            'home' => min(8, Service::query()->published()->count()),
        ];
    }

    /** @return array{demo_published: int, home_visible: int, demo_total: int} */
    public function run(): array
    {
        (new DemoCatalogSeeder)->seed(false);
        // Remise en ligne : un vendeur de démonstration suspendu masque ses services ; un profil dépublié (retrait précédent) n'apparaît plus dans l'annuaire.
        DB::table('users')->where('is_demo', true)->whereNotNull('suspended_at')->update(['suspended_at' => null]);
        DB::table('freelance_profiles')->where('is_demo', true)->whereNull('published_at')->update(['published_at' => now()]);

        return [
            'demo_published' => Service::query()->published()->where('is_demo', true)->count(),
            'home_visible' => min(8, Service::query()->published()->count()),
            'demo_total' => DB::table('services')->where('is_demo', true)->count(),
        ];
    }
}
