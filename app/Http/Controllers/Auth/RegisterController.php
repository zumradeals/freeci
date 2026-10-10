<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Modules\Accounts\Actions\RegisterUser;
use App\Modules\Accounts\Referrals\ReferralCodes;
use App\Modules\Accounts\Referrals\Referrals;
use App\Modules\Accounts\Security\EmailVerification;
use App\Modules\Notifications\Support\MailStatus;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(Request $request, ReferralCodes $codes): View
    {
        // Lien de parrainage : le code est mémorisé (30 jours) jusqu'à l'inscription ; un code invalide est ignoré sans bruit.
        $fromLink = ReferralCodes::normalize($request->query('parrain'));
        if ($fromLink !== null && config('freeci.referral.enabled')) {
            Cookie::queue('fc_ref', $fromLink, 60 * 24 * (int) config('freeci.referral.cookie_days'), null, null, $request->isSecure(), true, false, 'lax');
        }
        $code = config('freeci.referral.enabled') ? ($fromLink ?? ReferralCodes::normalize((string) $request->cookie('fc_ref'))) : null;
        $owner = $code ? $codes->ownerOf($code) : null;

        return view('auth.register', ['referralCode' => $owner ? $code : null, 'referrerName' => $owner ? ReferralCodes::shortName((string) $owner->name) : null]);
    }

    public function store(Request $request, RegisterUser $register, Referrals $referrals): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:254', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);

        $referralInput = config('freeci.referral.enabled') ? trim((string) $request->input('referral_code', '')) : '';
        if ($referralInput !== '') {
            $referrals->check($referralInput, $data['email']);          // code inconnu ou adresse identique : erreur claire sur le champ
        }

        $user = $register($data['name'], $data['email'], $data['password']);
        if ($referralInput !== '') {
            $referrals->attach($user, $referralInput);
            Cookie::queue(Cookie::forget('fc_ref'));
        }
        event(new Registered($user));
        if (MailStatus::configured()) {
            app(EmailVerification::class)->send($user);          // sans courrier réel : rien n'est envoyé, l'adresse reste non vérifiée
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('account.dashboard');
    }
}
