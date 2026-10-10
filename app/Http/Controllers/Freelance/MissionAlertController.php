<?php

namespace App\Http\Controllers\Freelance;

use App\Http\Controllers\Controller;
use App\Modules\Missions\Actions\MissionAlerts;
use App\Modules\Missions\Exceptions\MissionConflict;
use App\Modules\Missions\Queries\RecommendedMissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Alertes de missions (F-11) et « Missions pour vous » : le freelance gère ses propres alertes, rien d'autre. */
class MissionAlertController extends Controller
{
    public function index(Request $request, MissionAlerts $alerts): View
    {
        return view('freelance.alerts', ['alerts' => $alerts->list($request->user()), 'categories' => $alerts->categoryChoices(), 'max' => (int) config('freeci.missions.alerts.max'), 'space' => 'freelancer']);
    }

    public function store(Request $request, MissionAlerts $alerts): RedirectResponse
    {
        $d = $request->validate(['category' => ['required', 'string', 'max:80'], 'min_budget' => ['nullable', 'string', 'max:15']]);
        try {
            $alerts->create($request->user(), $d['category'], $d['min_budget'] ?? null);
        } catch (MissionConflict $e) {
            return redirect()->route('freelance.alerts')->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('freelance.alerts')->with('status', 'Alerte créée. Vous serez prévenu à la prochaine mission correspondante.');
    }

    public function toggle(Request $request, string $alert, MissionAlerts $alerts): RedirectResponse
    {
        $d = $request->validate(['active' => ['required', 'in:0,1']]);
        try {
            $alerts->setActive($request->user(), $alert, $d['active'] === '1');
        } catch (MissionConflict $e) {
            return redirect()->route('freelance.alerts')->with('error', $e->getMessage());
        }

        return redirect()->route('freelance.alerts')->with('status', $d['active'] === '1' ? 'Alerte activée.' : 'Alerte mise en pause : aucune notification tant qu’elle l’est.');
    }

    public function destroy(Request $request, string $alert, MissionAlerts $alerts): RedirectResponse
    {
        try {
            $alerts->delete($request->user(), $alert);
        } catch (MissionConflict $e) {
            return redirect()->route('freelance.alerts')->with('error', $e->getMessage());
        }

        return redirect()->route('freelance.alerts')->with('status', 'Alerte supprimée.');
    }

    public function recommended(Request $request, RecommendedMissions $recommended): View
    {
        return view('freelance.recommended', ['r' => $recommended->for($request->user()), 'space' => 'freelancer']);
    }
}
