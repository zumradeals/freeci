<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Pages privées : jamais de cache partagé ni de page conservée après déconnexion (docs/02 §8.2). */
class NoStore
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');

        return $response;
    }
}
