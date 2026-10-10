<?php

namespace App\Http\Controllers;

use App\Modules\Catalog\Actions\GetPublicProfile;
use App\Modules\Catalog\Exceptions\ServiceNotFound;
use App\Modules\Missions\Actions\MissionInvitations;
use App\Modules\Missions\Exceptions\MissionConflict;
use App\Modules\Missions\Exceptions\MissionForbidden;
use App\Modules\Missions\Queries\MissionInvitationQueries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Invitations envoyées par un client (F-12) : depuis le profil d'un freelance, et suivi sur la mission. */
class ClientInvitationController extends Controller
{
    public function create(Request $request, string $slug, GetPublicProfile $profile, MissionInvitationQueries $q): View
    {
        try {
            $p = $profile($slug);
        } catch (ServiceNotFound) {
            abort(404);
        }
        abort_if($p['userId'] === (string) $request->user()->getKey(), 404);

        return view('missions.invite', ['p' => $p, 'slug' => $slug, 'choices' => $q->inviteChoices($request->user(), $p['userId']), 'max' => (int) config('freeci.missions.invitations.message_max'), 'space' => 'client']);
    }

    public function store(Request $request, string $slug, MissionInvitations $actions): RedirectResponse
    {
        $d = $request->validate(['mission' => ['required', 'uuid'], 'message' => ['nullable', 'string', 'max:2000']]);
        try {
            $actions->invite($request->user(), $slug, $d['mission'], $d['message'] ?? null);
        } catch (MissionForbidden) {
            abort(404);
        } catch (MissionConflict $e) {
            return redirect()->route('invitations.create', $slug)->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('client.missions.invitations', $d['mission'])->with('status', 'Invitation envoyée. Le freelance décide de proposer ou non ; rien ne change pour les autres candidats.');
    }

    public function mission(Request $request, string $mission, MissionInvitationQueries $q): View
    {
        $title = $q->missionTitle($request->user(), $mission) ?? abort(404);

        return view('missions.invitations', ['title' => $title, 'missionId' => $mission, 'items' => $q->forMission($request->user(), $mission), 'max' => (int) config('freeci.missions.invitations.per_mission'), 'space' => 'client']);
    }

    public function withdraw(Request $request, string $invitation, MissionInvitations $actions): RedirectResponse
    {
        try {
            $actions->withdraw($request->user(), $invitation);
        } catch (MissionConflict $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Invitation retirée.');
    }
}
