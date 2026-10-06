<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Accounts\Security\AdminAccess;
use App\Modules\Accounts\Security\EmailVerification;
use App\Modules\Accounts\Security\TwoFactor;
use App\Modules\Notifications\Support\MailStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Parcours d'activation de l'administration : adresse vérifiée, puis double authentification, puis codes de récupération (affichés une fois). */
class ActivationController extends Controller
{
    public function __construct(private AdminAccess $access, private TwoFactor $mfa) {}

    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $s = $this->access->state($user, $request->session());
        if ($s['email'] && $s['mfa']) {
            return redirect()->route($s['session'] ? 'admin.home' : 'admin.mfa');
        }

        return view('admin.activation', ['user' => $user, 'emailOk' => $s['email'], 'mailConfigured' => MailStatus::configured(), 'pending' => $s['email'] ? $this->mfa->pending($user) : null]);
    }

    public function sendEmail(Request $request, EmailVerification $verification): RedirectResponse
    {
        $result = $verification->send($request->user());

        return back()->with(match ($result) {
            'sent' => ['status' => 'Un lien de vérification a été remis au serveur de courrier. Ouvrez-le depuis votre boîte e-mail (valable 60 minutes). La réception n’est pas garantie : vérifiez aussi vos courriers indésirables.'],
            'already' => ['status' => 'Votre adresse est déjà vérifiée.'],
            'unavailable' => ['error' => 'Le courrier n’est pas configuré sur cette installation : aucun courriel n’a été envoyé. Configurez le SMTP (voir la documentation) ou demandez l’attestation console.'],
            default => ['error' => 'L’envoi du courriel a échoué. Réessayez plus tard ou contrôlez la configuration du SMTP.'],
        });
    }

    public function begin(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->emailVerified() && ! $user->hasTwoFactor(), 403);
        if ($this->mfa->pending($user) === null) {
            $this->mfa->begin($user);
        }

        return redirect()->route('admin.activation');
    }

    public function confirm(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->emailVerified() && ! $user->hasTwoFactor(), 403);
        $data = $request->validate(['code' => ['required', 'string', 'max:20']]);
        if ($this->mfa->locked($user)) {
            return back()->withErrors(['code' => 'Trop de tentatives : réessayez dans '.ceil($this->mfa->secondsLocked($user) / 60).' minute(s).']);
        }
        $codes = $this->mfa->confirm($user, $data['code']);
        if ($codes === null) {
            return back()->withErrors(['code' => 'Code invalide. Vérifiez l’heure de votre téléphone et saisissez le code à 6 chiffres actuel.']);
        }
        $this->access->markMfaPassed($user, $request->session());
        $request->session()->regenerate();

        // Réponse directe (pas de redirection) : les codes ne sont jamais stockés dans la session ni rejouables.
        return view('admin.recovery-codes', ['codes' => $codes, 'first' => true]);
    }
}
