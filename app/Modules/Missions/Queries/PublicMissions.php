<?php

namespace App\Modules\Missions\Queries;

use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Missions\Exceptions\MissionForbidden;
use App\Modules\Missions\Models\Mission;
use App\Modules\Missions\Models\Proposal;
use App\Modules\Missions\Models\ProposalVersion;
use App\Shared\Dates;
use App\Shared\Money;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Découverte : missions OUVERTES (version publiée uniquement, date limite non dépassée). Projection publique à liste de champs explicite :
 * jamais l'identité ni les coordonnées du client, ni les réponses de brief, ni les propositions des autres candidats.
 */
final class PublicMissions
{
    public const SORTS = ['echeance', 'recentes', 'budget-croissant', 'budget-decroissant'];

    /**
     * Filtres : texte, catégorie, budget (min/max, FCFA), date limite de candidature dans N jours au plus. Aucune compétence n'est portée par une mission : pas de filtre
     * « compétence » ici. Pagination STABLE (identifiant en dernier) ; seuls les besoins OUVERTS et publiés sont listés.
     *
     * @param  array{budget_min?: mixed, budget_max?: mixed, delai?: mixed, tri?: ?string}  $filters
     */
    public function search(?string $q, ?string $categorySlug, int $perPage = 12, array $filters = []): LengthAwarePaginator
    {
        $int = fn (mixed $v, int $max) => is_numeric($v) && (int) $v > 0 && (int) $v <= $max ? (int) $v : null;
        $bmin = $int($filters['budget_min'] ?? null, 1_000_000_000);
        $bmax = $int($filters['budget_max'] ?? null, 1_000_000_000);
        if ($bmin !== null && $bmax !== null && $bmin > $bmax) {
            [$bmin, $bmax] = [$bmax, $bmin];
        }
        $days = $int($filters['delai'] ?? null, 365);
        $sort = in_array($filters['tri'] ?? null, self::SORTS, true) ? $filters['tri'] : 'echeance';
        $q = $q === null ? null : trim($q);
        $query = Mission::query()->where('missions.status', 'open')
            ->whereNotExists(fn ($w) => $w->select(DB::raw(1))->from('users')->whereColumn('users.id', 'missions.client_id')->whereNotNull('users.suspended_at'))
            ->join('mission_versions as v', 'v.id', '=', 'missions.published_version_id')->join('categories as c', 'c.id', '=', 'v.category_id')
            ->where('v.application_deadline', '>', now())
            ->when($categorySlug, fn ($w) => $w->where('c.slug', $categorySlug))
            ->where(function ($w) use ($q) {                // chaque mot saisi doit figurer dans le titre ou la description (accents ignorés)
                foreach (array_slice(preg_split('/\s+/u', (string) $q, -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 6) as $term) {
                    $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
                    $w->whereRaw('(freeci_unaccent(v.title) ILIKE freeci_unaccent(?) OR freeci_unaccent(v.description) ILIKE freeci_unaccent(?))', [$like, $like]);
                }
            })
            ->when($bmin !== null, fn ($w) => $w->where('v.budget_xof', '>=', $bmin))->when($bmax !== null, fn ($w) => $w->where('v.budget_xof', '<=', $bmax))
            ->when($days !== null, fn ($w) => $w->where('v.application_deadline', '<=', now()->addDays($days)))
            ->tap(fn ($w) => match ($sort) {
                'recentes' => $w->orderByDesc('missions.published_at'),
                'budget-croissant' => $w->orderBy('v.budget_xof'),
                'budget-decroissant' => $w->orderByDesc('v.budget_xof'),
                default => $w->orderBy('v.application_deadline'),
            })->orderBy('missions.id')->select('missions.slug', 'v.title', 'v.description', 'v.budget_xof', 'v.application_deadline', 'c.name as category', 'missions.is_demo');

        return $query->paginate($perPage)->withQueryString()->through(fn ($r) => [
            'slug' => $r->slug, 'title' => $r->title, 'excerpt' => mb_substr($r->description, 0, 220).(mb_strlen($r->description) > 220 ? '…' : ''), 'budget' => Money::xof((int) $r->budget_xof),
            'deadline' => Dates::format(Carbon::parse($r->application_deadline)), 'category' => $r->category, 'isDemo' => (bool) $r->is_demo,
        ]);
    }

    /** @return Collection<int, Category> */
    public function categoryChoices(): Collection
    {
        return Category::query()->active()->orderBy('position')->get(['id', 'name']);
    }

    /** @return list<array{slug: string, name: string}> */
    public function categories(): array
    {
        return Category::query()->active()->orderBy('position')->get(['slug', 'name'])->map(fn ($c) => ['slug' => $c->slug, 'name' => $c->name])->all();
    }

    /**
     * Fiche publique d'une mission (jamais un brouillon ni une mission en contrôle).
     *
     * @return array<string, mixed>
     */
    public function show(string $slug, ?User $viewer): array
    {
        $m = Mission::query()->where('slug', $slug)->whereNotNull('published_version_id')->with('publishedVersion.category')->first() ?? throw new MissionForbidden;
        $v = $m->publishedVersion;
        $accepting = $m->status === 'open' && $v->application_deadline->gt(now());
        $own = $viewer !== null && $viewer->getKey() === $m->client_id;

        $mine = null;
        if ($viewer !== null && ! $own) {            // le candidat ne voit QUE sa propre proposition
            $p = Proposal::query()->where('mission_id', $m->getKey())->where('freelancer_id', $viewer->getKey())->orderByDesc('created_at')->first();
            if ($p !== null) {
                $pv = ProposalVersion::query()->where('proposal_id', $p->getKey())->orderByDesc('number')->first();
                $mine = ['id' => $p->getKey(), 'state' => $p->state, 'number' => $pv->number, 'price' => Money::xof($pv->price_xof), 'stale' => $pv->mission_version_id !== $v->getKey(),
                    'validUntil' => Dates::format($pv->valid_until), 'expired' => $pv->valid_until->lte(now())];
            }
        }

        return [
            'slug' => $m->slug, 'title' => $v->title, 'description' => $v->description, 'category' => $v->category->name, 'budget' => Money::xof((int) $v->budget_xof),
            'deadline' => Dates::format($v->application_deadline), 'inputs' => $v->client_inputs, 'briefFiles' => $v->brief_requires_files, 'isDemo' => $m->is_demo,
            'accepting' => $accepting, 'status' => $m->status, 'own' => $own, 'mine' => $mine, 'missionId' => $m->getKey(), 'version' => $v->number,
        ];
    }
}
