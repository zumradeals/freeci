<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Accounts\Security\AdminAccess;
use App\Modules\Accounts\Security\SecurityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/** Confirmation récente d'identité (mot de passe) avant une opération sensible. Tentatives limitées et journalisées. */
class ReauthController extends Controller
{
    public function show(): View
    {
        return view('admin.reauth');
    }

    public function confirm(Request $request, AdminAccess $access): RedirectResponse
    {
        $user = $request->user();
        $key = 'reauth:'.$user->getKey();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['password' => 'Trop de tentatives : réessayez dans '.ceil(RateLimiter::availableIn($key) / 60).' minute(s).']);
        }
        $data = $request->validate(['password' => ['required', 'string', 'max:200']]);
        if (! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($key, 600);
            SecurityLog::record('reauth_failed', $user->getKey());

            return back()->withErrors(['password' => 'Mot de passe incorrect.']);
        }
        RateLimiter::clear($key);
        $access->markRecentlyConfirmed($user, $request->session());
        SecurityLog::record('reauth_ok', $user->getKey());
        $to = $request->session()->pull('admin_reauth_return');

        return redirect()->to(is_string($to) && str_starts_with($to, url('/admin')) ? $to : route('admin.home'));
    }
}
