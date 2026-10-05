<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Réservé aux administrateurs en vigueur. Pour les autres, l'espace n'existe pas (404) : on ne révèle pas son existence. */
class EnsureAdministrator
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isAdministrator(), 404);

        return $next($request);
    }
}
