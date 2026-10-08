<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Modules\Accounts\Actions\ProfilePhotos;
use App\Modules\Accounts\Queries\ProfilePhotoIds;
use App\Modules\Files\Exceptions\FileRejected;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Dépôt, remplacement et suppression de SA photo de profil (publique dès l'enregistrement). */
class ProfilePhotoController extends Controller
{
    public function store(Request $request, ProfilePhotos $photos, ProfilePhotoIds $ids): RedirectResponse
    {
        $request->validate(['photo' => ['required', 'file']], ['photo.required' => 'Choisissez une image.']);
        try {
            $photos->set($request->user(), $request->file('photo'));
        } catch (FileRejected $e) {
            return back()->with('error', $e->getMessage());
        }
        $ids->forget($request->user()->getKey());

        return back()->with('status', 'Votre photo est enregistrée. Elle est visible dès maintenant.');
    }

    public function destroy(Request $request, ProfilePhotos $photos, ProfilePhotoIds $ids): RedirectResponse
    {
        $done = $photos->delete($request->user());
        $ids->forget($request->user()->getKey());

        return back()->with('status', $done ? 'Photo supprimée : vos initiales s’affichent à la place.' : 'Aucune photo à supprimer.');
    }
}
