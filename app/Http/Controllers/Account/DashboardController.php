<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Modules\Orders\Queries\ClientOverview;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** Espace client : vraies données — actions attendues, commandes, état vide honnête. */
    public function __invoke(Request $request, ClientOverview $overview): View
    {
        return view('account.dashboard', ['user' => $request->user(), 'o' => $overview($request->user()), 'space' => 'client']);
    }
}
