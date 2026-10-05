<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Modules\Accounts\Actions\ResetPassword;
use App\Modules\Accounts\Actions\SendPasswordResetLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function request(): View
    {
        return view('auth.forgot-password');
    }

    public function email(Request $request, SendPasswordResetLink $send): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'string', 'email', 'max:254']]);
        $send($data['email']);

        // Même réponse que le compte existe ou non.
        return back()->with('status', 'Si cette adresse correspond à un compte, un lien de réinitialisation vient d’être envoyé. Il expire dans 60 minutes.');
    }

    public function edit(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => (string) $request->query('email', '')]);
    }

    public function update(Request $request, ResetPassword $reset): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:254'],
            'password' => ['required', 'string', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);

        if (! $reset($data['email'], $data['token'], $data['password'])) {
            throw ValidationException::withMessages(['email' => 'Ce lien est invalide, expiré ou déjà utilisé. Demandez-en un nouveau.']);
        }

        return redirect()->route('login')->with('status', 'Mot de passe modifié. Vous pouvez vous connecter.');
    }
}
