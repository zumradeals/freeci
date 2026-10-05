<?php

namespace App\Http\Controllers;

use App\Modules\Missions\Actions\MissionAuthoring;
use App\Modules\Missions\Actions\SelectProposal;
use App\Modules\Missions\Exceptions\MissionConflict;
use App\Modules\Missions\Exceptions\MissionForbidden;
use App\Modules\Missions\Queries\ClientMissions;
use App\Modules\Missions\Queries\MissionProposals;
use App\Modules\Missions\Queries\PublicMissions;
use App\Modules\Orders\Exceptions\OperationKeyReused;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** « Mes missions » (client) : rédaction, soumission, fermeture, réouverture, comparaison et sélection. Propriétaire seulement. */
class ClientMissionController extends Controller
{
    private const KINDS = ['soumettre' => 'submit', 'retirer-soumission' => 'unsubmit', 'nouvelle-version' => 'revise', 'fermer' => 'close', 'annuler' => 'cancel', 'rouvrir' => 'reopen'];

    public function __construct(private MissionAuthoring $authoring, private ClientMissions $missions) {}

    private function guard(callable $do, string $success, ?string $to = null, ?string $fallback = null): RedirectResponse
    {
        try {
            $do();
        } catch (MissionForbidden) {
            abort(404);
        } catch (MissionConflict $e) {
            return redirect($fallback ?? route('client.missions'))->with('error', $e->getMessage())->withInput();
        }

        return redirect($to ?? route('client.missions'))->with('status', $success);
    }

    public function index(Request $request): View
    {
        return view('missions.index', ['missions' => $this->missions->list($request->user()), 'space' => 'client']);
    }

    public function create(PublicMissions $pm): View
    {
        return view('missions.new', ['categories' => $pm->categoryChoices(), 'limits' => config('freeci.missions'), 'space' => 'client']);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['category_id' => ['required', 'string'], 'title' => ['required', 'string', 'max:300']], ['title.required' => 'Donnez un titre à votre besoin.', 'category_id.required' => 'Choisissez une catégorie.']);
        $m = $this->authoring->create($request->user(), $data['category_id'], $data['title']);

        return redirect()->route('client.missions.edit', $m->getKey())->with('status', 'Brouillon créé. Complétez-le puis soumettez-le à modération : il reste invisible du public jusqu’à son approbation.');
    }

    private function hub(Request $request, string $mission): array
    {
        try {
            return $this->missions->hub($request->user(), $mission);
        } catch (MissionForbidden) {
            abort(404);
        }
    }

    public function show(Request $request, string $mission): View
    {
        return view('missions.show', $this->hub($request, $mission) + ['space' => 'client']);
    }

    public function edit(Request $request, string $mission): View|RedirectResponse
    {
        $d = $this->hub($request, $mission);
        if ($d['working'] === null) {
            return redirect()->route('client.missions.show', $mission)->with('error', 'Aucune version en cours de rédaction : choisissez « Modifier la mission » pour en démarrer une.');
        }

        return view('missions.edit', $d + ['limits' => config('freeci.missions'), 'space' => 'client']);
    }

    public function update(Request $request, string $mission): RedirectResponse
    {
        $request->validate(['revision_no' => ['required', 'integer', 'min:1']]);
        $edit = route('client.missions.edit', $mission);
        $resp = $this->guard(fn () => $this->authoring->save($request->user(), $mission, $request->all(), (int) $request->input('revision_no')), 'Brouillon enregistré. Il n’est visible que de vous.', $edit, $edit);
        if ($request->input('intent') === 'submit' && ! $resp->getSession()?->has('error')) {
            return redirect()->route('client.missions.confirm', [$mission, 'soumettre']);
        }

        return $resp;
    }

    public function preview(Request $request, string $mission): View
    {
        try {
            $m = $this->missions->preview($request->user(), $mission);
        } catch (MissionForbidden) {
            abort(404);
        }

        return view('missions.public-show', ['m' => $m, 'preview' => route('client.missions.edit', $mission)]);
    }

    public function confirm(Request $request, string $mission, string $kind): View|RedirectResponse
    {
        $kind = self::KINDS[$kind];
        $d = $this->hub($request, $mission);
        $ok = match ($kind) {
            'submit' => $d['canSubmit'], 'unsubmit' => $d['canUnsubmit'], 'revise' => $d['canRevise'], 'close' => $d['canClose'], 'cancel' => $d['canCancel'], 'reopen' => $d['canReopen'],
        };
        if (! $ok) {
            return redirect()->route('client.missions.show', $mission)->with('error', 'Cette action n’est plus disponible : la mission a changé.');
        }

        return view('missions.step', $d + ['kind' => $kind, 'space' => 'client']);
    }

    public function act(Request $request, string $mission, string $kind): RedirectResponse
    {
        $u = $request->user();
        $kind = self::KINDS[$kind];
        $show = route('client.missions.show', $mission);

        return match ($kind) {
            'submit' => (function () use ($request, $u, $mission, $show) {
                $request->validate(['revision_no' => ['required', 'integer', 'min:1']]);

                return $this->guard(fn () => $this->authoring->submit($u, $mission, (int) $request->input('revision_no')), 'Mission soumise à modération. Elle reste invisible du public jusqu’à son approbation.', $show, $show);
            })(),
            'unsubmit' => $this->guard(fn () => $this->authoring->withdrawSubmission($u, $mission), 'Soumission retirée : la mission est de nouveau un brouillon modifiable.', route('client.missions.edit', $mission), $show),
            'revise' => $this->guard(fn () => $this->authoring->startRevision($u, $mission), 'Nouvelle version créée. La version publiée reste en ligne, et les propositions déjà reçues devront être reconfirmées après son approbation.', route('client.missions.edit', $mission), $show),
            'close' => $this->guard(fn () => $this->authoring->close($u, $mission, $request->input('note')), 'Mission fermée. L’historique et les propositions sont conservés.', $show, $show),
            'cancel' => $this->guard(fn () => $this->authoring->cancel($u, $mission), 'Mission annulée.', null, $show),
            'reopen' => $this->guard(fn () => $this->authoring->reopen($u, $mission), 'Mission rouverte : les propositions encore valides peuvent être retenues, les autres doivent être reconfirmées.', $show, $show),
        };
    }

    public function proposals(Request $request, string $mission, MissionProposals $proposals): View
    {
        try {
            $d = $proposals($request->user(), $mission, (array) $request->query('comparer', []));
        } catch (MissionForbidden) {
            abort(404);
        }

        return view('missions.proposals', $d + ['space' => 'client']);
    }

    public function selectForm(Request $request, string $mission, string $version, MissionProposals $proposals): View|RedirectResponse
    {
        try {
            $d = $proposals->forSelection($request->user(), $mission, $version);
        } catch (MissionForbidden) {
            abort(404);
        }
        if (! $d['item']['selectable']) {
            return redirect()->route('client.missions.proposals', $mission)->with('error', $d['item']['block'] ?? 'Cette proposition ne peut pas être retenue.');
        }

        return view('missions.select', $d + ['operationKey' => (string) Str::uuid(), 'space' => 'client']);
    }

    public function select(Request $request, string $mission, string $version, SelectProposal $select): RedirectResponse|Response
    {
        $data = $request->validate(['expected_version' => ['required', 'integer', 'min:1'], 'operation_key' => ['required', 'string', 'max:80'], 'answers' => ['nullable', 'array'], 'answers.*' => ['nullable', 'string'], 'notes' => ['nullable', 'string', 'max:3000']]);
        try {
            [$order, $replayed] = $select($request->user(), $mission, $version, (int) $data['expected_version'], $data['answers'] ?? [], $data['notes'] ?? null, (bool) $request->boolean('conditions'), $data['operation_key']);
        } catch (MissionForbidden) {
            abort(404);
        } catch (MissionConflict $e) {
            return redirect()->route('client.missions.proposals', $mission)->with('error', $e->getMessage());
        } catch (OperationKeyReused) {
            return redirect()->route('client.missions.proposals', $mission)->with('error', 'Ce formulaire a déjà été utilisé avec un contenu différent. Rechargez la page.');
        }

        return redirect()->route('orders.show', $order->reference)->with('status', $replayed ? 'Cette sélection avait déjà été enregistrée.' : 'Proposition retenue. La commande attend le paiement ; la mission ne sera attribuée qu’après paiement confirmé, et le travail ne commence qu’avec un brief complet.');
    }
}
