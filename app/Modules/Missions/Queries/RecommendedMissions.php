<?php

namespace App\Modules\Missions\Queries;

use App\Modules\Accounts\Models\User;
use App\Shared\Dates;
use App\Shared\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Missions pour vous (F-11) : missions ouvertes qui correspondent aux alertes ACTIVES du freelance (catégorie et budget minimum, rien d'autre). Sans aucune alerte,
 * les catégories de ses services publiés servent de repli, sans filtre de budget. Exclues : ses propres missions, celles où il a une proposition active ou retenue.
 */
final class RecommendedMissions
{
    /** @return array{items: list<array<string, mixed>>, mode: string} mode : alerts | services | paused | none */
    public function for(User $u, int $limit = 20): array
    {
        $alerts = DB::table('mission_alerts as a')->join('categories as c', 'c.id', '=', 'a.category_id')->where('a.user_id', $u->getKey())->get(['a.category_id', 'a.min_budget_xof', 'a.active', 'c.name']);
        $rules = [];
        if ($alerts->isNotEmpty()) {
            $mode = $alerts->where('active', true)->isEmpty() ? 'paused' : 'alerts';
            foreach ($alerts->where('active', true) as $a) {
                $rules[] = [$a->category_id, $a->min_budget_xof === null ? null : (int) $a->min_budget_xof, 'Alerte : '.$a->name.($a->min_budget_xof === null ? '' : ' · ≥ '.Money::xof((int) $a->min_budget_xof)->formatted())];
            }
        } else {
            $mode = 'services';
            $cats = DB::table('services as s')->join('freelance_profiles as p', 'p.id', '=', 's.freelance_profile_id')->join('categories as c', 'c.id', '=', 's.category_id')
                ->where('p.user_id', $u->getKey())->where('s.status', 'published')->distinct()->get(['c.id', 'c.name']);
            foreach ($cats as $c) {
                $rules[] = [$c->id, null, 'Catégorie de vos services : '.$c->name];
            }
            if ($rules === []) {
                $mode = 'none';
            }
        }
        if ($rules === []) {
            return ['items' => [], 'mode' => $mode];
        }
        $rows = DB::table('missions as m')->join('mission_versions as v', 'v.id', '=', 'm.published_version_id')->join('categories as c', 'c.id', '=', 'v.category_id')
            ->where('m.status', 'open')->where('v.application_deadline', '>', now())->where('m.client_id', '<>', $u->getKey())
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('users')->whereColumn('users.id', 'm.client_id')->whereNotNull('users.suspended_at'))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('proposals as pr')->whereColumn('pr.mission_id', 'm.id')->where('pr.freelancer_id', $u->getKey())->whereIn('pr.state', ['active', 'selected']))
            ->where(function ($w) use ($rules) {
                foreach ($rules as [$cat, $min]) {
                    $w->orWhere(fn ($x) => $x->where('v.category_id', $cat)->when($min !== null, fn ($y) => $y->where('v.budget_xof', '>=', $min)));
                }
            })->orderByDesc('m.published_at')->orderBy('m.id')->limit($limit)->get(['m.slug', 'v.title', 'v.budget_xof', 'v.application_deadline', 'v.category_id', 'c.name as category']);

        return ['mode' => $mode, 'items' => $rows->map(function ($r) use ($rules) {
            $why = null;
            foreach ($rules as [$cat, $min, $label]) {
                if ((string) $cat === (string) $r->category_id && ($min === null || (int) $r->budget_xof >= $min)) {
                    $why = $label;
                    break;
                }
            }

            return ['slug' => $r->slug, 'title' => $r->title, 'category' => $r->category, 'budget' => Money::xof((int) $r->budget_xof)->formatted().' FCFA', 'deadline' => Dates::format(Carbon::parse($r->application_deadline)), 'why' => $why];
        })->all()];
    }
}
