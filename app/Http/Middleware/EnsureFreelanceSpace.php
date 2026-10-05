<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Espace freelance : exige le rôle freelance ; sinon invitation à l'activer (docs/04 §7.3). */
class EnsureFreelanceSpace
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->hasRole('freelance')) {
            return redirect()->route('freelance.activate');
        }

        return $next($request);
    }
}
