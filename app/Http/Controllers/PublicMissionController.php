<?php

namespace App\Http\Controllers;

use App\Modules\Missions\Exceptions\MissionForbidden;
use App\Modules\Missions\Queries\PublicMissions;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Catalogue public des missions ouvertes et fiche d'une mission. */
class PublicMissionController extends Controller
{
    public function index(Request $request, PublicMissions $missions): View
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100'], 'categorie' => ['nullable', 'string', 'max:80']]);

        return view('missions.public-index', ['results' => $missions->search($request->query('q'), $request->query('categorie')), 'categories' => $missions->categories(), 'q' => (string) $request->query('q'), 'categorie' => (string) $request->query('categorie')]);
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
