<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** Premier lot : espace client sans commande ni mission, donc un état vide honnête. */
    public function __invoke(Request $request): View
    {
        return view('account.dashboard', ['user' => $request->user()]);
    }
}
