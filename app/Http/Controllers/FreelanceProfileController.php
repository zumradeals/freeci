<?php

namespace App\Http\Controllers;

use App\Modules\Catalog\Actions\FavoriteQueries;
use App\Modules\Catalog\Actions\GetPublicProfile;
use App\Modules\Catalog\Exceptions\ServiceNotFound;
use App\Modules\Orders\Queries\ReviewQueries;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Page publique d'un freelance : champs publics et services publiés seulement. */
class FreelanceProfileController extends Controller
{
    public function show(Request $request, string $slug, GetPublicProfile $get, ReviewQueries $reviews, FavoriteQueries $favorites): View
    {
        try {
            $p = $get($slug);
        } catch (ServiceNotFound) {
            abort(404);
        }
        $stats = $reviews->forProfiles([$p['id']])[$p['id']] ?? null;
        $p['rating'] = $stats;
        $p['favorited'] = isset($favorites->marked($request->user(), 'freelance', [$p['id']])[$p['id']]);

        return view('catalog.profile', ['p' => $p, 'slug' => $slug, 'reviews' => $reviews->pageForProfile($p['id'], max(1, (int) $request->query('avis', 1)))->withQueryString()]);
    }
}
