<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminHomeController extends Controller
{
    /** Coquille d'administration : aucune fonction pour l'instant (modération, support, paramètres : lots ultérieurs). */
    public function __invoke(Request $request): View
    {
        return view('admin.home', ['user' => $request->user()]);
    }
}
