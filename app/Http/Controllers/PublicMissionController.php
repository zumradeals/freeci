<?php

namespace App\Http\Controllers;

use App\Modules\Catalog\Actions\ListCategories;
use App\Modules\Missions\Exceptions\MissionForbidden;
use App\Modules\Missions\Queries\PublicMissions;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Catalogue public des missions ouvertes et fiche d'une mission. */
class PublicMissionController extends Controller
{
    public function index(Request $request, PublicMissions $missions, ListCategories $catalog): View
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100'], 'categorie' => ['nullable', 'string', 'max:80'], 'budget_min' => ['nullable', 'integer', 'min:1'], 'budget_max' => ['nullable', 'integer', 'min:1'], 'delai' => ['nullable', 'integer', 'min:1', 'max:365'], 'tri' => ['nullable', 'string', 'max:20']]);
        $f = ['budget_min' => (string) $request->query('budget_min'), 'budget_max' => (string) $request->query('budget_max'), 'delai' => (string) $request->query('delai'), 'tri' => (string) $request->query('tri', 'echeance')];

        return view('missions.public-index', ['results' => $missions->search($request->query('q'), $request->query('categorie'), 9, $f), 'categories' => $catalog->withServiceCounts(), 'q' => (string) $request->query('q'), 'categorie' => (string) $request->query('categorie'), 'f' => $f]);
    }

    public function show(Request $request, string $slug, PublicMissions $missions): View
    {
        try {
            $m = $missions->show($slug, $request->user());
        } catch (MissionForbidden) {
            abort(404);
        }

        return view('missions.public-show', ['m' => $m]);
    }
}
