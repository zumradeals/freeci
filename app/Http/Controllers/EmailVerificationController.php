<?php

namespace App\Http\Controllers;

use App\Modules\Accounts\Security\EmailVerification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    /** Lien signé reçu par courriel : valable seulement pour l'utilisateur connecté qu'il désigne. */
    public function verify(Request $request, string $id, string $hash, EmailVerification $verification): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->getKey() === $id, 403);
        $ok = $verification->confirm($user, $hash);

        return redirect()->route($user->isAdministrator() ? 'admin.activation' : 'account.dashboard')
            ->with($ok ? ['status' => 'Votre adresse e-mail est vérifiée.'] : ['error' => 'Ce lien ne correspond plus à votre adresse. Demandez-en un nouveau.']);
    }
}
