<?php

namespace App\Http\Controllers;

use App\Modules\Catalog\Actions\GetPublicProfile;
use App\Modules\Catalog\Exceptions\ServiceNotFound;
use Illuminate\View\View;

/** Page publique d'un freelance : champs publics et services publiés seulement. */
class FreelanceProfileController extends Controller
{
    public function show(string $slug, GetPublicProfile $get): View
    {
        try {
            $p = $get($slug);
        } catch (ServiceNotFound) {
            abort(404);
        }

        return view('catalog.profile', ['p' => $p]);
    }
}
