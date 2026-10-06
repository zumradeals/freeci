<?php

namespace App\Modules\Catalog\Actions;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Compétences RÉELLEMENT déclarées par des profils publiés (regroupées sans casse ni accents), les plus fréquentes d'abord. Aucune compétence inventée. */
final class ListSkills
{
    /** @return list<array{name: string, count: int}> */
    public function __invoke(int $limit = 60): array
    {
        $rows = DB::select(
            'select min(sk.v) as name, count(distinct p.id) as c from freelance_profiles p cross join lateral jsonb_array_elements_text(p.skills) as sk(v)
             where p.published_at is not null and trim(sk.v) <> \'\' group by lower(freeci_unaccent(sk.v)) order by c desc, name asc limit ?', [max(1, min($limit, 200))],
        );

        return array_map(fn ($r) => ['name' => (string) $r->name, 'count' => (int) $r->c], $rows);
    }

    /** Condition SQL réutilisable : le profil désigné par `$profileColumn` déclare cette compétence. */
    public static function whereHasSkill(Builder|\Illuminate\Database\Eloquent\Builder $q, string $profileColumn, string $skill): void
    {
        $q->whereExists(fn ($x) => $x->select(DB::raw(1))->from('freelance_profiles as sp')->whereColumn('sp.id', $profileColumn)
            ->whereRaw('exists (select 1 from jsonb_array_elements_text(sp.skills) as k(v) where freeci_unaccent(lower(k.v)) = freeci_unaccent(lower(?)))', [$skill]));
    }
}
