<?php

namespace App\Http\Controllers;

use App\Modules\Catalog\Actions\ListCategories;
use App\Modules\Catalog\Actions\ListSkills;
use App\Modules\Catalog\Actions\SearchFreelancers;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Annuaire public des freelances : filtres et tri dans l'URL (GET), même rendu sans JavaScript. */
class FreelanceDirectoryController extends Controller
{
    public function index(Request $request, SearchFreelancers $search, ListCategories $categories, ListSkills $skills): View
    {
        $f = [
            'q' => mb_substr((string) $request->query('q'), 0, 100), 'categorie' => mb_substr((string) $request->query('categorie'), 0, 80), 'competence' => mb_substr((string) $request->query('competence'), 0, 60),
            'prix_max' => (string) $request->query('prix_max'), 'delai_max' => (string) $request->query('delai_max'), 'tri' => (string) $request->query('tri', 'recents'),
        ];
        $results = $search($f['q'], $f['categorie'], $f['competence'], $f['prix_max'], $f['delai_max'], $f['tri'], max(1, (int) $request->query('page', 1)), $request->user())->withQueryString();

        return view('catalog.freelances', ['results' => $results, 'f' => $f, 'categories' => $categories->withServiceCounts(), 'skills' => $skills()]);
    }
}
