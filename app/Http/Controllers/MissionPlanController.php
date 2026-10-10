<?php

namespace App\Http\Controllers;

use App\Modules\Missions\Actions\MissionPlans;
use App\Modules\Missions\Exceptions\MissionConflict;
use App\Modules\Missions\Exceptions\MissionForbidden;
use App\Modules\Missions\Queries\PlanQueries;
use App\Modules\Orders\Exceptions\InvalidTransition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Plan de jalons d'une mission (F-13) : suivi pour le client et le freelance ; reprise du paiement et arrêt réservés au client. */
class MissionPlanController extends Controller
{
    public function show(Request $request, string $mission, PlanQueries $plans): View
    {
        $p = $plans->forMission($request->user(), $mission) ?? abort(404);

        return view('missions.plan', ['p' => $p, 'space' => $p['isClient'] ? 'client' : 'freelancer']);
    }

    public function reopen(Request $request, string $mission, MissionPlans $plans): RedirectResponse
    {
        try {
            $reference = $plans->reopen($request->user(), $mission);
        } catch (MissionForbidden) {
            abort(404);
        } catch (MissionConflict $e) {
            return redirect()->route('milestones.show', $mission)->with('error', $e->getMessage());
        }

        return redirect()->route('orders.show', $reference)->with('status', 'Le paiement du jalon est rouvert. Vous avez 24 h pour le régler.');
    }

    public function stopForm(Request $request, string $mission, PlanQueries $plans): View|RedirectResponse
    {
        $p = $plans->forMission($request->user(), $mission) ?? abort(404);
        abort_unless($p['isClient'], 404);
        if (! $p['canStop']) {
            return redirect()->route('milestones.show', $mission)->with('error', 'Le plan ne peut pas être arrêté pour le moment (aucun jalon validé, jalon en cours de réalisation, ou plan déjà terminé).');
        }

        return view('missions.plan-stop', ['p' => $p, 'space' => 'client']);
    }

    public function stop(Request $request, string $mission, MissionPlans $plans): RedirectResponse
    {
        $request->validate(['confirm' => ['accepted']]);
        try {
            $plans->stop($request->user(), $mission);
        } catch (MissionForbidden) {
            abort(404);
        } catch (MissionConflict|InvalidTransition $e) {
            return redirect()->route('milestones.show', $mission)->with('error', $e instanceof MissionConflict ? $e->getMessage() : 'Un paiement est en cours sur le jalon : réessayez une fois son issue connue.');
        }

        return redirect()->route('milestones.show', $mission)->with('status', 'Plan arrêté. Les jalons restants sont annulés sans paiement.');
    }
}
