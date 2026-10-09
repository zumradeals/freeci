<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Modules\Accounts\Security\AdminAccess;
use App\Modules\Accounts\Security\LoginSecondStep;
use App\Modules\Accounts\Security\TwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Deuxième étape de la connexion pour les comptes qui ont activé la double authentification (code de l'application, ou code de secours à usage unique).
 * La session n'est ouverte qu'ici, après un code valide. Une personne de l'équipe franchit du même coup la double authentification de l'administration.
 */
class TwoFactorChallengeController extends Controller
{
    public function __construct(private LoginSecondStep $step, private TwoFactor $mfa, private AdminAccess $access) {}

    public function show(Request $request): View|RedirectResponse
    {
        if ($this->step->pendingUserId($request->session()) === null) {
            return redirect()->route('login');
        }

        return view('auth.two-factor', ['contact' => config('freeci.legal.contact_email')]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $id = $this->step->pendingUserId($request->session());
        $user = $id === null ? null : Auth::guard('web')->getProvider()->retrieveById($id);
        if ($user === null || ! $user->hasTwoFactor()) {
            $this->step->clear($request->session());

            return redirect()->route('login')->with('error', 'La vérification a expiré : reconnectez-vous.');
        }
        $data = $request->validate(['code' => ['required', 'string', 'max:20']]);
        if ($this->mfa->locked($user)) {
            return back()->withErrors(['code' => 'Trop de tentatives : réessayez dans '.max(1, (int) ceil($this->mfa->secondsLocked($user) / 60)).' minute(s).']);
        }
        if (! $this->mfa->challenge($user, $data['code'])) {
            return back()->withErrors(['code' => 'Code invalide ou déjà utilisé.']);
        }

        $this->step->clear($request->session());
        Auth::guard('web')->login($user, false);          // jamais de connexion mémorisée avec la double authentification
        $request->session()->regenerate();
        $this->access->forget($request->session());
        if ($user->isStaff()) {
            $this->access->markMfaPassed($user, $request->session());
        }
        $remaining = $this->mfa->remaining($user);

        return redirect()->intended(route('account.dashboard'))
            ->with($remaining <= 2 ? ['error' => "Il vous reste {$remaining} code(s) de secours : générez-en de nouveaux depuis « Mon compte »."] : []);
    }

    public function cancel(Request $request): RedirectResponse
    {
        $this->step->clear($request->session());

        return redirect()->route('login');
    }
}
