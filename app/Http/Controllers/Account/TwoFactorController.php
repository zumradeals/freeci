<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Modules\Accounts\Actions\AccountSettings;
use App\Modules\Accounts\Security\QrSvg;
use App\Modules\Accounts\Security\SecondFactorGate;
use App\Modules\Accounts\Security\TwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Double authentification FACULTATIVE de la personne connectée (obligatoire pour l'équipe, qui ne peut pas la désactiver ici).
 * Activation : secret en attente, confirmé par un code valide + mot de passe ; codes de secours remis UNE fois. Régénération et désactivation : mot de passe + code.
 */
class TwoFactorController extends Controller
{
    public function __construct(private TwoFactor $mfa, private SecondFactorGate $gate, private AccountSettings $settings) {}

    public function show(Request $request): View|RedirectResponse
    {
        $u = $request->user();
        if ($u->hasTwoFactor()) {
            return redirect(route('account.settings').'#h-2fa');
        }
        $p = $this->mfa->pending($u) ?? $this->mfa->begin($u);

        return view('account.two-factor', ['space' => $this->space($request), 'qr' => QrSvg::for($p['uri']), 'key' => implode(' ', str_split($p['secret'], 4))]);
    }

    public function enable(Request $request): View|RedirectResponse
    {
        $u = $request->user();
        abort_if($u->hasTwoFactor(), 404);
        $d = $request->validate(['code' => ['required', 'string', 'max:20'], 'current_password' => ['required', 'string', 'max:200']]);
        $this->settings->assertPassword($u, $d['current_password']);
        if ($this->mfa->locked($u)) {
            return back()->withErrors(['code' => 'Trop de tentatives : réessayez dans '.max(1, (int) ceil($this->mfa->secondsLocked($u) / 60)).' minute(s).']);
        }
        $codes = $this->mfa->confirm($u, $d['code']);
        if ($codes === null) {
            return back()->withErrors(['code' => 'Code invalide. Vérifiez l’heure de votre téléphone et saisissez le code actuellement affiché.']);
        }
        $this->settings->revokeOthers($u, $request->session()->getId());      // les autres sessions n'ont pas franchi de deuxième étape

        return view('account.two-factor-codes', ['codes' => $codes, 'first' => true, 'space' => $this->space($request)]);
    }

    public function regenerate(Request $request): View
    {
        $u = $request->user();
        abort_unless($u->hasTwoFactor(), 404);
        $d = $request->validate(['current_password' => ['required', 'string', 'max:200'], 'code' => ['nullable', 'string', 'max:20']]);
        $this->settings->assertPassword($u, $d['current_password']);
        $this->gate->assert($u, $d['code'] ?? null);

        return view('account.two-factor-codes', ['codes' => $this->mfa->regenerate($u), 'first' => false, 'space' => $this->space($request)]);
    }

    public function disable(Request $request): RedirectResponse
    {
        $u = $request->user();
        abort_unless($u->hasTwoFactor(), 404);
        if ($u->isStaff()) {
            return back()->with('error', 'La double authentification est obligatoire pour l’équipe : elle ne peut pas être désactivée.');
        }
        $d = $request->validate(['current_password' => ['required', 'string', 'max:200'], 'code' => ['nullable', 'string', 'max:20']]);
        $this->settings->assertPassword($u, $d['current_password']);
        $this->gate->assert($u, $d['code'] ?? null);
        $this->mfa->disable($u, $request->session()->getId());

        return redirect()->route('account.settings')->with('status', 'Double authentification désactivée. Vos autres sessions ont été fermées.');
    }

    private function space(Request $request): string
    {
        return $request->user()->hasRole('freelance') && $request->query('espace') === 'freelance' ? 'freelancer' : 'client';
    }
}
