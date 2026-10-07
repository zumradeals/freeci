<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Actions\ManageAdministrators;
use App\Modules\Admin\Actions\ManageSupportTeam;
use App\Modules\Catalog\Exceptions\ModerationDenied;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Équipe d'assistance : habilitation « support » accordée ou retirée par l'administrateur. */
class SupportTeamController extends Controller
{
    public function index(ManageSupportTeam $team, ManageAdministrators $admins): View
    {
        return view('admin.team', ['members' => $team->members(), 'admins' => $admins->members(), 'phrase' => ManageAdministrators::PHRASE, 'me' => auth()->id()]);
    }

    public function grantAdmin(Request $request, ManageAdministrators $admins): RedirectResponse
    {
        $d = $request->validate(['email' => ['required', 'email', 'max:254'], 'reason' => ['required', 'string', 'min:10', 'max:1000'], 'phrase' => ['required', 'string', 'max:100'], 'confirm' => ['accepted'], 'until' => ['nullable', 'date', 'after:today']], ['confirm.accepted' => 'Cochez la case pour confirmer que vous accordez ce pouvoir en connaissance de cause.']);
        try {
            $admins->grant($request->user(), $d['email'], $d['reason'], isset($d['until']) ? now()->parse($d['until'])->endOfDay() : null, $d['phrase']);
        } catch (ModerationDenied|\DomainException $e) {
            return back()->withInput($request->except('phrase'))->with('error', $e->getMessage());
        }

        return back()->with('status', 'Habilitation d’administrateur accordée. L’accès s’ouvre quand la personne a vérifié son adresse et activé la double authentification.');
    }

    public function revokeAdmin(Request $request, ManageAdministrators $admins, string $id): RedirectResponse
    {
        $d = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000'], 'confirm' => ['accepted']], ['confirm.accepted' => 'Cochez la case pour confirmer le retrait.']);
        try {
            $admins->revoke($request->user(), $id, $d['reason']);
        } catch (ModerationDenied|\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Habilitation d’administrateur retirée (ses rôles client et freelance sont conservés).');
    }

    public function grant(Request $request, ManageSupportTeam $team): RedirectResponse
    {
        $d = $request->validate(['email' => ['required', 'email', 'max:254'], 'reason' => ['required', 'string', 'min:10', 'max:1000'], 'until' => ['nullable', 'date', 'after:today']]);
        try {
            $team->grant($request->user(), $d['email'], $d['reason'], isset($d['until']) ? now()->parse($d['until'])->endOfDay() : null);
        } catch (ModerationDenied|\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Habilitation accordée. L’accès s’ouvre quand la personne a vérifié son adresse et activé la double authentification.');
    }

    public function revoke(Request $request, ManageSupportTeam $team, string $id): RedirectResponse
    {
        $d = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']]);
        try {
            $team->revoke($request->user(), $id, $d['reason']);
        } catch (ModerationDenied|\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Habilitation retirée : plus aucun accès aux dossiers ; les dossiers affectés sont libérés.');
    }
}
