<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Accounts\Security\TwoFactor;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SecurityController extends Controller
{
    public function show(Request $request, TwoFactor $mfa): View
    {
        return view('admin.security', ['user' => $request->user(), 'remaining' => $mfa->remaining($request->user())]);
    }

    /** Régénère les codes de récupération (opération sensible : confirmation récente exigée). Les anciens cessent de fonctionner. */
    public function regenerate(Request $request, TwoFactor $mfa): View
    {
        return view('admin.recovery-codes', ['codes' => $mfa->regenerate($request->user()), 'first' => false]);
    }
}
