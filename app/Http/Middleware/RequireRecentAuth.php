<?php

namespace App\Http\Middleware;

use App\Modules\Accounts\Security\AdminAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Actes sensibles : exige une confirmation d'identité (mot de passe) datant de moins de N minutes. */
class RequireRecentAuth
{
    public function __construct(private AdminAccess $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->access->recentlyConfirmed($request->user(), $request->session())) {
            $request->session()->put('admin_reauth_return', $request->isMethod('GET') ? $request->fullUrl() : (url()->previous() ?: route('admin.home')));

            return redirect()->route('admin.reauth')->with('error', 'Confirmez votre identité pour poursuivre : cette opération est sensible.');
        }

        return $next($request);
    }
}
