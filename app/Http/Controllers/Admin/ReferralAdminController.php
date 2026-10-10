<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Actions\PromoCampaigns;
use App\Modules\Admin\Queries\ReferralAdmin;
use App\Modules\Catalog\Exceptions\ModerationDenied;
use App\Modules\Finance\Commission\CommissionGrants;
use App\Modules\Missions\Exceptions\MissionConflict;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Campagnes promotionnelles et attributions de commission offerte (F-14) : administrateurs seulement ; écritures avec confirmation récente d'identité et journal d'audit. */
class ReferralAdminController extends Controller
{
    public function index(ReferralAdmin $q): View
    {
        return view('admin.referrals', ['d' => $q()]);
    }

    public function create(Request $request, PromoCampaigns $campaigns): RedirectResponse
    {
        return $this->run(fn () => $campaigns->create($request->user(), $request->all()), 'Campagne créée.');
    }

    public function update(Request $request, string $id, PromoCampaigns $campaigns): RedirectResponse
    {
        return $this->run(fn () => $campaigns->update($request->user(), $id, $request->all()), 'Campagne modifiée.');
    }

    public function state(Request $request, string $id, string $action, PromoCampaigns $campaigns): RedirectResponse
    {
        return $this->run(fn () => $campaigns->setState($request->user(), $id, $action === 'reprendre'), $action === 'reprendre' ? 'Campagne réactivée.' : 'Campagne suspendue : plus aucune nouvelle utilisation.');
    }

    public function revoke(Request $request, string $grant, CommissionGrants $grants): RedirectResponse
    {
        $d = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        return $this->run(fn () => $grants->revoke($request->user(), $grant, $d['reason']), 'Attribution révoquée : le bénéficiaire est prévenu. Les accords déjà figés ne changent pas.');
    }

    private function run(callable $do, string $ok): RedirectResponse
    {
        try {
            $do();
        } catch (MissionConflict|ModerationDenied $e) {
            return redirect()->route('admin.referrals')->with('error', $e->getMessage());
        }

        return redirect()->route('admin.referrals')->with('status', $ok);
    }
}
