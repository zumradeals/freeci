<?php

namespace App\Http\Middleware;

use App\Modules\Accounts\Security\SecurityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Réservé aux administrateurs en vigueur (revérifié à CHAQUE requête). Pour les autres, l'espace n'existe pas (404) : on ne révèle pas son existence. */
class EnsureAdministrator
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user?->isAdministrator()) {
            if ($user !== null) {
                SecurityLog::record('admin_denied', $user->getKey());
            }
            abort(404);
        }

        return $next($request);
    }
}
