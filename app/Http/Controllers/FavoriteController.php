<?php

namespace App\Http\Controllers;

use App\Modules\Catalog\Actions\FavoriteQueries;
use App\Modules\Catalog\Actions\ToggleFavorite;
use App\Modules\Catalog\Exceptions\ServiceNotFound;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Favoris privés de l'utilisateur connecté. */
class FavoriteController extends Controller
{
    public function index(Request $request, FavoriteQueries $q): View
    {
        return view('account.favorites', ['items' => $q->list($request->user()), 'space' => $request->user()->hasRole('freelance') && $request->query('espace') === 'freelance' ? 'freelancer' : 'client']);
    }

    public function toggle(Request $request, ToggleFavorite $toggle, string $kind, string $slug): RedirectResponse
    {
        $d = $request->validate(['intent' => ['required', 'in:add,remove']]);
        try {
            $on = $toggle($request->user(), $kind, $slug, $d['intent']);
        } catch (ServiceNotFound) {
            return back()->with('error', 'Ce contenu n’est plus disponible : il ne peut pas être ajouté à vos favoris.');
        }

        return back()->with('status', $on ? 'Ajouté à vos favoris (privés : personne d’autre ne les voit).' : 'Retiré de vos favoris.');
    }

    public function remove(Request $request, FavoriteQueries $q, int $id): RedirectResponse
    {
        $q->remove($request->user(), $id);

        return back()->with('status', 'Retiré de vos favoris.');
    }
}
