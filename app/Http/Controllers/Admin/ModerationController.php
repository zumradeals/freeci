<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Actions\ModerateContent;
use App\Modules\Admin\Queries\ModerationQueue;
use App\Modules\Catalog\Exceptions\ModerationDenied;
use App\Modules\Catalog\Exceptions\ServiceStateConflict;
use App\Modules\Missions\Exceptions\MissionConflict;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ModerationController extends Controller
{
    public function __construct(private ModerationQueue $queue, private ModerateContent $moderate) {}

    public function index(Request $request): View
    {
        $me = $request->user()->getKey();
        $tab = in_array($request->query('onglet'), ['missions', 'en-ligne', 'suspendus'], true) ? $request->query('onglet') : 'services';
        $kind = $request->query('type') === 'mission' ? 'mission' : 'service';
        $data = ['tab' => $tab, 'counts' => $this->queue->counts(), 'kind' => $kind, 'q' => (string) $request->query('q', '')];
        if ($tab === 'services') {
            $data['rows'] = $this->queue->pendingServices($me);
        } elseif ($tab === 'missions') {
            $data['rows'] = $this->queue->pendingMissions($me);
        } else {
            $data['page'] = $this->queue->live($kind, $tab === 'suspendus' ? 'suspended' : 'live', $request->query('q'), $me, (int) config('freeci.admin.page_size'));
        }

        return view('admin.moderation.index', $data);
    }

    public function service(Request $request, string $version): View
    {
        $d = $this->queue->service($version, $request->user()->getKey());
        abort_if($d === null, 404);

        return view('admin.moderation.review', ['d' => $d, 'kind' => 'service']);
    }

    public function mission(Request $request, string $version): View
    {
        $d = $this->queue->mission($version, $request->user()->getKey());
        abort_if($d === null, 404);

        return view('admin.moderation.review', ['d' => $d, 'kind' => 'mission']);
    }

    public function liveService(Request $request, string $id): View
    {
        $d = $this->queue->liveService($id, $request->user()->getKey());
        abort_if($d === null, 404);

        return view('admin.moderation.live', ['d' => $d, 'kind' => 'service']);
    }

    public function liveMission(Request $request, string $id): View
    {
        $d = $this->queue->liveMission($id, $request->user()->getKey());
        abort_if($d === null, 404);

        return view('admin.moderation.live', ['d' => $d, 'kind' => 'mission']);
    }

    /** Décisions sur une version en contrôle : approuver ou refuser (motif). */
    public function decide(Request $request, string $kind, string $version, string $decision): RedirectResponse
    {
        abort_unless(in_array($kind, ['service', 'mission'], true) && in_array($decision, ['approuver', 'refuser'], true), 404);
        $reason = $decision === 'refuser' ? $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']])['reason'] : null;
        $admin = $request->user();

        return $this->attempt(function () use ($kind, $decision, $admin, $version, $reason) {
            match ([$kind, $decision]) {
                ['service', 'approuver'] => $this->moderate->approveService($admin, $version),
                ['service', 'refuser'] => $this->moderate->refuseService($admin, $version, $reason),
                ['mission', 'approuver'] => $this->moderate->approveMission($admin, $version),
                ['mission', 'refuser'] => $this->moderate->refuseMission($admin, $version, $reason),
            };
        }, $decision === 'approuver' ? 'Version approuvée et publiée. L’auteur est notifié.' : 'Correction demandée avec votre motif. L’auteur est notifié.', route('admin.moderation', ['onglet' => $kind === 'service' ? 'services' : 'missions']));
    }

    /** Suspension / remise en ligne (opérations sensibles : confirmation récente exigée par la route). */
    public function toggle(Request $request, string $kind, string $id, string $action): RedirectResponse
    {
        abort_unless(in_array($kind, ['service', 'mission'], true) && in_array($action, ['suspendre', 'remettre'], true), 404);
        $reason = $action === 'suspendre' ? $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']])['reason'] : null;
        $admin = $request->user();

        return $this->attempt(function () use ($kind, $action, $admin, $id, $reason) {
            match ([$kind, $action]) {
                ['service', 'suspendre'] => $this->moderate->suspendService($admin, $id, $reason),
                ['service', 'remettre'] => $this->moderate->reinstateService($admin, $id),
                ['mission', 'suspendre'] => $this->moderate->suspendMission($admin, $id, $reason),
                ['mission', 'remettre'] => $this->moderate->reinstateMission($admin, $id),
            };
        }, $action === 'suspendre' ? 'Contenu suspendu. L’auteur est notifié ; ses commandes en cours ne sont pas affectées.' : 'Contenu remis en ligne. L’auteur est notifié.', route('admin.moderation', ['onglet' => 'en-ligne', 'type' => $kind]));
    }

    private function attempt(callable $do, string $success, string $to): RedirectResponse
    {
        try {
            $do();
        } catch (ModerationDenied|ServiceStateConflict|MissionConflict $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->to($to)->with('status', $success);
    }
}
