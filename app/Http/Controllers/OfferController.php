<?php

namespace App\Http\Controllers;

use App\Modules\Orders\Actions\CustomOffers;
use App\Modules\Orders\Exceptions\OfferConflict;
use App\Modules\Orders\Queries\OfferQueries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Offre personnalisée (F-09) : le freelance de la conversation l'envoie et la retire ; le client l'examine, l'accepte (ce qui crée la commande) ou la refuse. Participants seulement. */
class OfferController extends Controller
{
    public function __construct(private CustomOffers $offers, private OfferQueries $queries) {}

    public function create(Request $request, string $conversation): View|RedirectResponse
    {
        $ctx = $this->queries->composeContext($request->user(), $conversation);
        abort_if($ctx === null, 404);
        if (! $ctx['can']) {
            return redirect()->route('messages.show', ['conversation' => $conversation, 'espace' => 'freelance'])
                ->with('error', 'Vous ne pouvez pas envoyer d’offre dans cette conversation : elle est liée à une commande, une offre est déjà en attente, ou les échanges sont suspendus.');
        }

        return view('offers.create', ['conversation' => $ctx['id'], 'with' => $ctx['with'], 'context' => $ctx['context'], 'operationKey' => (string) Str::uuid(), 'space' => 'freelancer']);
    }

    public function store(Request $request, string $conversation): RedirectResponse
    {
        $in = $request->validate(['operation_key' => ['required', 'string', 'max:80'], 'title' => ['required', 'string', 'max:300'], 'scope' => ['required', 'string', 'max:6000'], 'deliverables' => ['nullable', 'string', 'max:4000'],
            'client_inputs' => ['nullable', 'string', 'max:2000'], 'price_xof' => ['required', 'string', 'max:12'], 'delivery_days' => ['required', 'string', 'max:4'], 'revisions_included' => ['required', 'string', 'max:3'],
            'delivery_files' => ['nullable', 'in:1'], 'valid_days' => ['required', 'string', 'max:3']]);
        $in['delivery_mode'] = $request->boolean('delivery_files') ? 'files' : 'message';
        try {
            [, $replayed] = $this->offers->send($request->user(), $conversation, $in, $in['operation_key']);
        } catch (OfferConflict $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('messages.show', ['conversation' => $conversation, 'espace' => 'freelance'])->with('status', $replayed ? 'Cette offre a déjà été envoyée.' : 'Offre envoyée. Elle apparaît dans la conversation ; vous pouvez la retirer tant qu’elle n’est pas acceptée.');
    }

    public function show(Request $request, string $offer): View
    {
        $o = $this->queries->find($request->user(), $offer);
        abort_if($o === null, 404);

        return view('offers.show', ['o' => $o, 'operationKey' => (string) Str::uuid(), 'space' => $o['mine'] ? 'freelancer' : 'client']);
    }

    public function accept(Request $request, string $offer): RedirectResponse
    {
        $d = $request->validate(['operation_key' => ['required', 'string', 'max:80'], 'answers' => ['nullable', 'array', 'max:10'], 'answers.*' => ['nullable', 'string', 'max:1000'], 'notes' => ['nullable', 'string', 'max:3000'], 'conditions' => ['nullable']]);
        try {
            [$order, $replayed] = $this->offers->accept($request->user(), $offer, array_values($d['answers'] ?? []), $d['notes'] ?? null, ! empty($d['conditions']), $d['operation_key']);
        } catch (OfferConflict $e) {
            return redirect()->route('offers.show', $offer)->with('error', $e->getMessage());
        }

        return redirect()->route('orders.show', $order->reference)->with('status', $replayed ? 'Cette offre a déjà été acceptée : voici la commande.' : 'Offre acceptée : la commande est créée. Vous avez maintenant le délai de paiement pour la régler. Aucun paiement n’a encore été demandé.');
    }

    public function decline(Request $request, string $offer): RedirectResponse
    {
        $d = $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        try {
            $this->offers->decline($request->user(), $offer, $d['note'] ?? null);
        } catch (OfferConflict $e) {
            return redirect()->route('offers.show', $offer)->with('error', $e->getMessage());
        }

        return redirect()->route('offers.show', $offer)->with('status', 'Offre refusée : le freelance en est informé.');
    }

    public function withdraw(Request $request, string $offer): RedirectResponse
    {
        try {
            $this->offers->withdraw($request->user(), $offer);
        } catch (OfferConflict $e) {
            return redirect()->route('offers.show', $offer)->with('error', $e->getMessage());
        }

        return redirect()->route('offers.show', $offer)->with('status', 'Offre retirée. Vous pouvez en envoyer une autre depuis la conversation.');
    }
}
