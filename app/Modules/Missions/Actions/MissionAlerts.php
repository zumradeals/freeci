<?php

namespace App\Modules\Missions\Actions;

use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Support\Availability;
use App\Modules\Missions\Exceptions\MissionConflict;
use App\Modules\Notifications\Actions\Notify;
use App\Shared\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Alertes de missions (F-11) : UNE catégorie et un budget minimum facultatif, rien d'autre (ni mots-clés, ni lecture des messages).
 * Notification à la première publication d'une mission, une seule par mission et par freelance, plafonnée par jour ; aucune pendant l'indisponibilité.
 */
final class MissionAlerts
{
    public function __construct(private Notify $notify) {}

    /** @return list<array{id: string, category: string, categoryId: string, min: ?int, minLabel: string, active: bool, recent: int}> */
    public function list(User $user): array
    {
        $since = now()->subDays(14);

        return DB::table('mission_alerts as a')->join('categories as c', 'c.id', '=', 'a.category_id')->where('a.user_id', $user->getKey())->orderBy('a.created_at')->orderBy('a.id')
            ->get(['a.id', 'a.category_id', 'a.min_budget_xof', 'a.active', 'c.name'])
            ->map(function ($r) use ($since, $user) {
                $recent = DB::table('missions as m')->join('mission_versions as v', 'v.id', '=', 'm.published_version_id')->where('v.category_id', $r->category_id)
                    ->where('m.published_at', '>=', $since)->where('m.client_id', '<>', $user->getKey())
                    ->when($r->min_budget_xof, fn ($q) => $q->where('v.budget_xof', '>=', $r->min_budget_xof))->count();

                return ['id' => (string) $r->id, 'category' => $r->name, 'categoryId' => (string) $r->category_id, 'min' => $r->min_budget_xof === null ? null : (int) $r->min_budget_xof,
                    'minLabel' => $r->min_budget_xof === null ? 'Tous les budgets' : 'Budget à partir de '.Money::xof((int) $r->min_budget_xof)->formatted().' FCFA', 'active' => (bool) $r->active, 'recent' => $recent];
            })->all();
    }

    /** @return list<array{id: string, name: string, slug: string}> */
    public function categoryChoices(): array
    {
        return Category::query()->active()->orderBy('position')->get(['id', 'name', 'slug'])->map(fn ($c) => ['id' => (string) $c->getKey(), 'name' => $c->name, 'slug' => $c->slug])->all();
    }

    public function create(User $user, string $category, mixed $minBudget): string
    {
        $cat = Category::query()->active()->where(fn ($q) => $q->where('slug', $category)->when(Str::isUuid($category), fn ($w) => $w->orWhere('id', $category)))->first()
            ?? throw new MissionConflict('Choisissez une catégorie.');
        $min = null;
        if ($minBudget !== null && trim((string) $minBudget) !== '') {
            $digits = preg_replace('/[\s\x{00A0}\x{202F}]/u', '', (string) $minBudget);
            [$lo, $hi] = config('freeci.missions.budget_xof');
            if (! ctype_digit($digits) || (int) $digits < 1 || (int) $digits > $hi) {
                throw new MissionConflict('Le budget minimum doit être un nombre entier de francs, au plus '.number_format($hi, 0, ',', ' ').'.');
            }
            $min = (int) $digits;
        }

        return DB::transaction(function () use ($user, $cat, $min) {
            DB::table('users')->where('id', $user->getKey())->lockForUpdate()->first();          // sérialise les créations d'un même compte
            if (DB::table('mission_alerts')->where('user_id', $user->getKey())->count() >= (int) config('freeci.missions.alerts.max')) {
                throw new MissionConflict('Vous avez atteint le maximum de '.config('freeci.missions.alerts.max').' alertes : supprimez-en une pour en créer une autre.');
            }
            $exists = DB::table('mission_alerts')->where('user_id', $user->getKey())->where('category_id', $cat->getKey())->whereRaw('COALESCE(min_budget_xof, 0) = ?', [$min ?? 0])->exists();
            if ($exists) {
                throw new MissionConflict('Cette alerte existe déjà.');
            }
            $id = (string) Str::uuid();
            DB::table('mission_alerts')->insert(['id' => $id, 'user_id' => $user->getKey(), 'category_id' => $cat->getKey(), 'min_budget_xof' => $min, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);

            return $id;
        });
    }

    public function setActive(User $user, string $id, bool $active): void
    {
        if (DB::table('mission_alerts')->where('id', $id)->where('user_id', $user->getKey())->update(['active' => $active, 'updated_at' => now()]) === 0) {
            throw new MissionConflict('Alerte introuvable.');
        }
    }

    public function delete(User $user, string $id): void
    {
        if (DB::table('mission_alerts')->where('id', $id)->where('user_id', $user->getKey())->delete() === 0) {
            throw new MissionConflict('Alerte introuvable.');
        }
    }

    /**
     * À appeler après la PREMIÈRE publication d'une mission (jamais pour une nouvelle version). Idempotent : la clé de notification est unique par mission et par destinataire.
     *
     * @return int notifications créées
     */
    public function notifyPublished(string $missionId): int
    {
        $m = DB::table('missions as m')->join('mission_versions as v', 'v.id', '=', 'm.published_version_id')->join('categories as c', 'c.id', '=', 'v.category_id')
            ->where('m.id', $missionId)->where('m.status', 'open')->first(['m.slug', 'm.client_id', 'v.category_id', 'v.budget_xof', 'v.title', 'c.name as category']);
        if ($m === null) {
            return 0;
        }
        $to = DB::table('mission_alerts as a')->join('users as u', 'u.id', '=', 'a.user_id')->join('freelance_profiles as p', 'p.user_id', '=', 'u.id')
            ->where('a.active', true)->where('a.category_id', $m->category_id)->where(fn ($q) => $q->whereNull('a.min_budget_xof')->orWhere('a.min_budget_xof', '<=', $m->budget_xof))
            ->where('a.user_id', '<>', $m->client_id)->whereNull('u.suspended_at')->whereNotNull('p.published_at')
            ->whereRaw('NOT '.Availability::unavailableSql('p'))->distinct()->pluck('a.user_id');
        $cap = (int) config('freeci.missions.alerts.daily_notifications');
        $day = Carbon::now()->startOfDay();
        $n = 0;
        foreach ($to as $userId) {
            $sent = DB::table('app_notifications')->where('user_id', $userId)->where('type', 'mission_alert')->where('created_at', '>=', $day)->count();
            if ($sent >= $cap) {
                continue;
            }
            $budget = Money::xof((int) $m->budget_xof)->formatted().' FCFA';
            $n += ($this->notify)((string) $userId, 'mission_alert', 'mission_alert:'.$missionId, 'Nouvelle mission : '.$m->title, $m->category.' · budget '.$budget, 'missions.show', ['slug' => $m->slug]) ? 1 : 0;
        }

        return $n;
    }
}
