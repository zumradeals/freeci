<?php

namespace App\Http\Middleware;

use App\Modules\Accounts\Security\SecurityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Personnel habilité (administrateur ou assistance) en vigueur, revérifié à chaque requête. Sinon : 404, tentative journalisée. */
class EnsureStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user?->isStaff()) {
            if ($user !== null) {
                SecurityLog::record('admin_denied', $user->getKey());
            }
            abort(404);
        }

        return $next($request);
    }
}
