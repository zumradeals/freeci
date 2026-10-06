<?php

namespace App\Http\Middleware;

use App\Modules\Accounts\Security\AdminAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Les fonctions d'administration ne s'ouvrent que si TOUS les prérequis sont réunis : adresse vérifiée, double authentification
 * activée, double authentification franchie dans cette session. Sinon : parcours d'activation ou défi, jamais un accès silencieux.
 * À placer APRÈS « administrator ».
 */
class EnsureAdminReady
{
    public function __construct(private AdminAccess $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        $s = $this->access->state($request->user(), $request->session());
        if (! $s['email'] || ! $s['mfa']) {
            return redirect()->route('admin.activation');
        }
        if (! $s['session']) {
            $request->session()->put('admin_intended', $request->isMethod('GET') ? $request->fullUrl() : route('admin.home'));

            return redirect()->route('admin.mfa');
        }

        return $next($request);
    }
}
