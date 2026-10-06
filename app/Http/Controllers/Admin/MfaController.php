<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Accounts\Security\AdminAccess;
use App\Modules\Accounts\Security\TwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Défi de double authentification pour la session (code de l'application d'authentification, ou code de récupération à usage unique). */
class MfaController extends Controller
{
    public function __construct(private AdminAccess $access, private TwoFactor $mfa) {}

    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user->emailVerified() || ! $user->hasTwoFactor()) {
            return redirect()->route('admin.activation');
        }
        if ($this->access->mfaPassed($user, $request->session())) {
            return redirect()->route('admin.home');
        }

        return view('admin.mfa');
    }

    public function verify(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->emailVerified() && $user->hasTwoFactor(), 403);
        $data = $request->validate(['code' => ['required', 'string', 'max:20']]);
        if ($this->mfa->locked($user)) {
            return back()->withErrors(['code' => 'Trop de tentatives : réessayez dans '.ceil($this->mfa->secondsLocked($user) / 60).' minute(s).']);
        }
        if (! $this->mfa->challenge($user, $data['code'])) {
            return back()->withErrors(['code' => 'Code invalide ou déjà utilisé.']);
        }
        $this->access->markMfaPassed($user, $request->session());
        $request->session()->regenerate();
        $to = $request->session()->pull('admin_intended');
        $remaining = $this->mfa->remaining($user);

        return redirect()->to(is_string($to) && str_starts_with($to, url('/admin')) ? $to : route('admin.home'))
            ->with($remaining <= 2 ? ['error' => "Il vous reste {$remaining} code(s) de récupération : régénérez-en depuis « Ma sécurité »."] : []);
    }
}
