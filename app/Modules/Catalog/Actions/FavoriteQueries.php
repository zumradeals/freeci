<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Models\FreelanceProfile;
use App\Modules\Catalog\Models\Service;
use Illuminate\Support\Facades\DB;

/** Lectures des favoris : TOUJOURS bornées à l'utilisateur connecté (jamais visibles des autres). Un contenu retiré ou suspendu est « indisponible » sans aucun détail. */
final class FavoriteQueries
{
    /**
     * @param  list<string>  $targetIds
     * @return array<string, true> identifiants déjà en favoris
     */
    public function marked(?User $user, string $kind, array $targetIds): array
    {
        if ($user === null || $targetIds === []) {
            return [];
        }

        return DB::table('favorites')->where('user_id', $user->getKey())->where('kind', $kind)->whereIn('target_id', $targetIds)->pluck('target_id')->mapWithKeys(fn ($id) => [$id => true])->all();
    }

    /** @return list<array<string, mixed>> */
    public function list(User $user): array
    {
        $rows = DB::table('favorites')->where('user_id', $user->getKey())->orderByDesc('id')->limit(200)->get();
        $services = Service::query()->published()->with(['category', 'freelanceProfile'])->whereIn('services.id', $rows->where('kind', 'service')->pluck('target_id')->all())->get()->keyBy('id');
        $profiles = FreelanceProfile::query()->whereNotNull('published_at')->whereIn('id', $rows->where('kind', 'freelance')->pluck('target_id')->all())->get()->keyBy('id');

        return $rows->map(function ($r) use ($services, $profiles) {
            $base = ['id' => $r->id, 'kind' => $r->kind, 'available' => false, 'card' => null, 'profile' => null];
            if ($r->kind === 'service' && isset($services[$r->target_id])) {
                $base['available'] = true;
                $base['card'] = ServiceProjection::card($services[$r->target_id]);
            }
            if ($r->kind === 'freelance' && isset($profiles[$r->target_id])) {
                $p = $profiles[$r->target_id];
                $base['available'] = true;
                $base['profile'] = ['userId' => (string) $p->user_id, 'slug' => $p->slug, 'name' => $p->display_name, 'initials' => ServiceProjection::initials($p->display_name), 'headline' => $p->headline, 'city' => $p->city];
            }

            return $base;
        })->all();
    }

    public function remove(User $user, int $favoriteId): void
    {
        DB::table('favorites')->where('id', $favoriteId)->where('user_id', $user->getKey())->delete();
    }
}
