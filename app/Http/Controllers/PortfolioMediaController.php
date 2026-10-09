<?php

namespace App\Http\Controllers;

use App\Modules\Accounts\Queries\PortfolioAccess;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Seul point d'accès aux images des réalisations (réencodées, sur le disque privé, jamais dans `public/`). */
class PortfolioMediaController extends Controller
{
    public function show(Request $request, string $id, string $variant, PortfolioAccess $access): BinaryFileResponse
    {
        abort_unless(preg_match('/^[0-9a-f-]{36}$/', $id) === 1, 404);
        $file = $access($id, $variant, $request->user()) ?? abort(404);
        $response = response()->file($file['path'], [
            'Content-Type' => $file['mime'], 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'",
            'Cache-Control' => $file['public'] ? 'public, max-age=300' : 'private, no-store',
        ]);
        if (! $file['public']) {
            $response->setPrivate();
        }

        return $response;
    }
}
