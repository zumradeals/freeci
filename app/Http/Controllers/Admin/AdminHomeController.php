<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Queries\AdminDashboard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminHomeController extends Controller
{
    public function __invoke(Request $request, AdminDashboard $dashboard): View|RedirectResponse
    {
        if (! $request->user()->isAdministrator()) {
            return redirect()->route('admin.support');          // personnel « support » : son espace est l'assistance
        }

        return view('admin.home', ['d' => $dashboard()]);
    }
}
