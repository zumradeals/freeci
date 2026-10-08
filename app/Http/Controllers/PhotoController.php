<?php

namespace App\Http\Controllers;

use App\Modules\Accounts\Queries\ProfilePhotoAccess;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Seul point d'accès aux photos de profil (réencodées, sur le disque privé, jamais dans `public/`). */
class PhotoController extends Controller
{
    public function show(Request $request, string $id, string $variant, ProfilePhotoAccess $access): BinaryFileResponse
    {
        abort_unless(preg_match('/^[0-9a-f-]{36}$/', $id) === 1, 404);
        $file = $access($id, $variant, $request->user()) ?? abort(404);

        $response = response()->file($file['path'], [
            'Content-Type' => $file['mime'], 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'",
            'Cache-Control' => $file['public'] ? 'public, max-age=300' : 'private, no-store',
        ]);
        if (! $file['public']) {
            $response->setPrivate();                       // BinaryFileResponse force « public » par défaut
        }

        return $response;
    }
}
