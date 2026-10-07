<?php

namespace App\Modules\Admin\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Admin\Settings\SettingsConflict;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Retrait des données de démonstration (is_demo = true), et d'elles SEULES. Jamais de donnée réelle, jamais une catégorie.
 * Ce qui est référencé par une commande, un message, une proposition ou un dossier est CONSERVÉ (historique en ajout seul) : un service
 * de démonstration conservé est archivé (il quitte le catalogue public), un profil conservé est dépublié. Chaque suppression est isolée :
 * une contrainte inattendue conserve l'élément concerné sans interrompre le reste.
 */
final class PurgeDemoData
{
    public function __construct(private AdminAudit $audit) {}

    /** @return array{services: int, profiles: int, users: int, missions: int, orders: int} */
    public static function counts(): array
    {
        return [
            'services' => DB::table('services')->where('is_demo', true)->count(),
            'profiles' => DB::table('freelance_profiles')->where('is_demo', true)->count(),
            'users' => DB::table('users')->where('is_demo', true)->count(),
            'missions' => DB::table('missions')->where('is_demo', true)->count(),
            'orders' => DB::table('orders')->where('is_demo', true)->count(),
        ];
    }

    /** Depuis l'administration : motif, confirmation, journal d'audit. @return array<string, int> */
    public function byAdmin(User $admin, string $reason): array
    {
        return $this->audit->run($admin, 'demo.purge', 'demo', null, 'Données de démonstration', $reason, function () use ($reason) {
            if (mb_strlen(trim($reason)) < 10) {
                throw new SettingsConflict('Indiquez le motif (10 caractères minimum).');
            }

            return $this->run();
        });
    }

    /** @return array<string, int> compteurs « supprimés » et « conservés » */
    public function run(): array
    {
        $r = ['services_removed' => 0, 'services_kept' => 0, 'missions_removed' => 0, 'missions_kept' => 0, 'profiles_removed' => 0, 'profiles_kept' => 0, 'users_removed' => 0, 'users_kept' => 0];

        foreach (DB::table('services')->where('is_demo', true)->pluck('id') as $id) {
            $this->attempt(function () use ($id) {
                DB::table('favorites')->where('kind', 'service')->where('target_id', $id)->delete();
                DB::table('services')->where('id', $id)->delete();
            }) ? $r['services_removed']++ : $r['services_kept']++;
        }
        // Les services de démonstration conservés (commandes, conversations) quittent le catalogue public.
        DB::table('services')->where('is_demo', true)->whereIn('status', ['draft', 'in_review', 'published', 'suspended'])->update(['status' => 'archived', 'updated_at' => now()]);

        foreach (DB::table('missions')->where('is_demo', true)->pluck('id') as $id) {
            $this->attempt(function () use ($id) {
                DB::table('mission_versions')->where('mission_id', $id)->delete();
                DB::table('missions')->where('id', $id)->delete();
            }) ? $r['missions_removed']++ : $r['missions_kept']++;
        }

        foreach (DB::table('freelance_profiles')->where('is_demo', true)->pluck('id') as $id) {
            $removed = $this->attempt(function () use ($id) {
                DB::table('favorites')->where('kind', 'freelance')->where('target_id', $id)->delete();
                DB::table('freelance_profiles')->where('id', $id)->delete();
            });
            $removed ? $r['profiles_removed']++ : $r['profiles_kept']++;
            if (! $removed) {
                DB::table('freelance_profiles')->where('id', $id)->update(['published_at' => null, 'updated_at' => now()]);
            }
        }

        foreach (DB::table('users')->where('is_demo', true)->pluck('id') as $id) {
            $this->attempt(fn () => DB::table('users')->where('id', $id)->delete()) ? $r['users_removed']++ : $r['users_kept']++;
        }

        return $r;
    }

    /** Une suppression dans un point de sauvegarde : refusée par une contrainte → conservée, le reste continue. */
    private function attempt(callable $do): bool
    {
        try {
            DB::transaction($do);

            return true;
        } catch (QueryException) {
            return false;
        }
    }
}
