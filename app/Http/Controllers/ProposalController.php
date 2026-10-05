<?php

namespace App\Http\Controllers;

use App\Modules\Missions\Actions\ProposalActions;
use App\Modules\Missions\Exceptions\MissionConflict;
use App\Modules\Missions\Exceptions\MissionForbidden;
use App\Modules\Missions\Queries\FreelancerProposals;
use App\Modules\Missions\Queries\PublicMissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Propositions d'un freelance : formulaire (création ou révision), liste personnelle, retrait. Auteur seulement. */
class ProposalController extends Controller
{
    public function index(Request $request, FreelancerProposals $proposals): View
    {
        return view('missions.my-proposals', ['proposals' => $proposals->list($request->user()), 'space' => 'freelancer']);
    }

    public function form(Request $request, string $slug, PublicMissions $missions, FreelancerProposals $proposals): View|RedirectResponse
    {
        try {
            $m = $missions->show($slug, $request->user());
        } catch (MissionForbidden) {
            abort(404);
        }
        if ($m['own']) {
            return redirect()->route('missions.show', $slug)->with('error', 'Vous ne pouvez pas candidater à votre propre mission.');
        }
        if (! $m['accepting']) {
            return redirect()->route('missions.show', $slug)->with('error', 'Cette mission n’accepte plus de propositions.');
        }
        $current = $proposals->current($request->user(), $m['missionId']);
        if (($current['state'] ?? null) === 'selected') {
            return redirect()->route('missions.show', $slug)->with('error', 'Votre proposition est retenue : elle ne peut plus être modifiée.');
        }

        return view('missions.proposal', ['m' => $m, 'p' => $current, 'limits' => config('freeci.missions.proposal'), 'space' => 'freelancer']);
    }

    public function store(Request $request, string $slug, PublicMissions $missions, ProposalActions $actions): RedirectResponse
    {
        $request->validate(['expected_number' => ['required', 'integer', 'min:0']]);
        try {
            $m = $missions->show($slug, $request->user());
            $actions->submit($request->user(), $m['missionId'], $request->all(), (int) $request->input('expected_number'));
        } catch (MissionForbidden) {
            abort(404);
        } catch (MissionConflict $e) {
            return redirect()->route('missions.show', $slug)->with('error', $e->getMessage());
        }

        return redirect()->route('freelance.proposals')->with('status', 'Proposition enregistrée. Seuls vous et le client la voyez ; chaque version est conservée et le client en sélectionne une précise.');
    }

    public function withdrawForm(Request $request, string $proposal, FreelancerProposals $proposals): View|RedirectResponse
    {
        try {
            $p = $proposals->ownedProposal($request->user(), $proposal);
        } catch (MissionForbidden) {
            abort(404);
        }
        if ($p['state'] !== 'active') {
            return redirect()->route('freelance.proposals')->with('error', 'Cette proposition ne peut plus être retirée.');
        }

        return view('missions.proposal-withdraw', ['p' => $p, 'space' => 'freelancer']);
    }

    public function withdraw(Request $request, string $proposal, ProposalActions $actions): RedirectResponse
    {
        try {
            $actions->withdraw($request->user(), $proposal);
        } catch (MissionForbidden) {
            abort(404);
        } catch (MissionConflict $e) {
            return redirect()->route('freelance.proposals')->with('error', $e->getMessage());
        }

        return redirect()->route('freelance.proposals')->with('status', 'Proposition retirée. Ses versions sont conservées.');
    }
}
