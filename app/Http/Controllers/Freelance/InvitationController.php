<?php

namespace App\Http\Controllers\Freelance;

use App\Http\Controllers\Controller;
use App\Modules\Missions\Actions\MissionInvitations;
use App\Modules\Missions\Exceptions\MissionConflict;
use App\Modules\Missions\Queries\MissionInvitationQueries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Invitations reçues par un freelance (F-12) : il décide librement de proposer ou de décliner. */
class InvitationController extends Controller
{
    public function index(Request $request, MissionInvitationQueries $q): View
    {
        return view('freelance.invitations', ['items' => $q->forFreelancer($request->user()), 'reasons' => MissionInvitations::REASONS, 'space' => 'freelancer']);
    }

    public function decline(Request $request, string $invitation, MissionInvitations $actions): RedirectResponse
    {
        $d = $request->validate(['reason' => ['required', 'string', 'max:20']]);
        try {
            $actions->decline($request->user(), $invitation, $d['reason']);
        } catch (MissionConflict $e) {
            return redirect()->route('freelance.invitations')->with('error', $e->getMessage());
        }

        return redirect()->route('freelance.invitations')->with('status', 'Invitation déclinée. Le client voit le motif choisi.');
    }
}
