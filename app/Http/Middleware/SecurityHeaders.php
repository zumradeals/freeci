<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** En-têtes de sécurité communs à toutes les pages (docs/07 §6). */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $h = $response->headers;

        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('X-Frame-Options', 'DENY');
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $h->set('Cross-Origin-Opener-Policy', 'same-origin');

        $hsts = (int) config('freeci.hsts_max_age');
        if ($hsts > 0 && $request->isSecure()) {
            $h->set('Strict-Transport-Security', 'max-age='.$hsts);
        }
        if (config('freeci.noindex')) {
            $h->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
