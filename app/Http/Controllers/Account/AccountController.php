<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Modules\Accounts\Actions\AccountClosure;
use App\Modules\Accounts\Actions\AccountSettings;
use App\Modules\Accounts\Actions\ChangeEmail;
use App\Modules\Accounts\Actions\ExportPersonalData;
use App\Modules\Accounts\Exceptions\AccountConflict;
use App\Modules\Accounts\Security\SecondFactorGate;
use App\Modules\Accounts\Security\TwoFactor;
use App\Modules\Notifications\Support\MailStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Compte de l'utilisateur connecté : informations, mot de passe, adresse, sessions, export, fermeture. Tout est borné à l'utilisateur de la session. */
class AccountController extends Controller
{
    public function show(Request $request, AccountSettings $settings, ChangeEmail $email, AccountClosure $closure): View
    {
        $u = $request->user();
        $open = $closure->open($u);

        return view('account.settings', [
            'space' => $u->hasRole('freelance') && $request->query('espace') === 'freelance' ? 'freelancer' : 'client',
            'sessions' => $settings->sessions($u, $request->session()->getId()),
            'pendingEmail' => $email->pending($u),
            'mailReady' => MailStatus::configured(),
            'closure' => $open,
            'blockers' => $open === null ? [] : $closure->blockers($u->getKey()),
            'graceDays' => (int) config('freeci.account.closure_grace_days'),
            'isStaff' => $u->isStaff(),
            'twoFactor' => $u->hasTwoFactor() ? ['since' => $u->two_factor_confirmed_at, 'remaining' => app(TwoFactor::class)->remaining($u)] : null,
        ]);
    }

    public function name(Request $request, AccountSettings $settings): RedirectResponse
    {
        $settings->updateName($request->user(), (string) $request->input('name'));

        return back()->with('status', 'Votre nom a été mis à jour.');
    }

    public function password(Request $request, AccountSettings $settings): RedirectResponse
    {
        $d = $request->validate(['current_password' => ['required', 'string', 'max:200'], 'password' => ['required', 'string', 'confirmed', Password::min(10)->letters()->numbers()], 'code' => ['nullable', 'string', 'max:20']]);
        $settings->assertPassword($request->user(), $d['current_password']);
        app(SecondFactorGate::class)->assert($request->user(), $d['code'] ?? null);        // double authentification active : un code s'ajoute au mot de passe
        $settings->changePassword($request->user(), $d['current_password'], $d['password'], $request->session()->getId());

        return back()->with('status', 'Mot de passe modifié. Vos autres sessions ont été fermées.');
    }

    public function requestEmail(Request $request, ChangeEmail $email): RedirectResponse
    {
        $d = $request->validate(['email' => ['required', 'string', 'max:254'], 'current_password' => ['required', 'string', 'max:200'], 'code' => ['nullable', 'string', 'max:20']]);
        app(AccountSettings::class)->assertPassword($request->user(), $d['current_password']);
        app(SecondFactorGate::class)->assert($request->user(), $d['code'] ?? null);
        try {
            $email->request($request->user(), $d['email'], $d['current_password']);
        } catch (AccountConflict $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Si cette adresse est utilisable, un lien de vérification vient de lui être envoyé. Votre adresse actuelle reste active jusqu’à la confirmation.');
    }

    public function confirmEmail(Request $request, ChangeEmail $email, string $id, string $token): RedirectResponse
    {
        $r = $email->confirm($request->user(), $id, $token);

        return redirect()->route('account.settings')->with($r === 'ok' ? 'status' : 'error', match ($r) {
            'ok' => 'Votre nouvelle adresse est vérifiée et remplace l’ancienne (qui en a été informée).',
            'taken' => 'Cette adresse n’est plus disponible. Votre adresse actuelle est conservée.',
            default => 'Ce lien n’est plus valide (expiré, déjà utilisé ou destiné à un autre compte). Refaites la demande.',
        });
    }

    public function revokeSession(Request $request, AccountSettings $settings, string $id): RedirectResponse
    {
        try {
            $settings->revoke($request->user(), $id, $request->session()->getId());
        } catch (AccountConflict $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Session fermée.');
    }

    public function revokeOthers(Request $request, AccountSettings $settings): RedirectResponse
    {
        $n = $settings->revokeOthers($request->user(), $request->session()->getId());

        return back()->with('status', $n > 0 ? "{$n} autre(s) session(s) fermée(s)." : 'Aucune autre session ouverte.');
    }

    /** Export : exige le mot de passe, borné par jour, réponse privée jamais mise en cache, aucun fichier conservé sur le serveur. */
    public function export(Request $request, AccountSettings $settings, ExportPersonalData $export): Response|RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'string', 'max:200']]);
        $u = $request->user();
        $settings->assertPassword($u, (string) $request->input('current_password'));
        $key = 'export:'.$u->getKey();
        if (RateLimiter::tooManyAttempts($key, (int) config('freeci.account.export_per_day'))) {
            return back()->with('error', 'Limite d’exports atteinte pour aujourd’hui. Réessayez demain.');
        }
        RateLimiter::hit($key, 86400);
        $json = json_encode($export->build($u), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return response($json, 200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="freeci-mes-donnees-'.now()->format('Ymd').'.json"',
            'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function requestClosure(Request $request, AccountClosure $closure): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'string', 'max:200'], 'confirm' => ['accepted']], ['confirm.accepted' => 'Cochez la case pour confirmer avoir lu les conséquences.']);
        try {
            $closure->request($request->user(), (string) $request->input('current_password'));
        } catch (AccountConflict $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Demande de fermeture enregistrée. Vous pouvez l’annuler jusqu’à son exécution.');
    }

    public function cancelClosure(Request $request, AccountClosure $closure): RedirectResponse
    {
        $closure->cancel($request->user());

        return back()->with('status', 'Demande de fermeture annulée.');
    }
}
