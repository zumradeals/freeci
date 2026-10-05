<?php

namespace App\Http\Controllers;

use App\Modules\Orders\Actions\AcceptServiceRequest;
use App\Modules\Orders\Actions\CancelBeforePayment;
use App\Modules\Orders\Actions\DeclineServiceRequest;
use App\Modules\Orders\Actions\WithdrawServiceRequest;
use App\Modules\Orders\Exceptions\InvalidTransition;
use App\Modules\Orders\Exceptions\OperationKeyReused;
use App\Modules\Orders\Exceptions\OrderExpired;
use App\Modules\Orders\Exceptions\OrderForbidden;
use App\Modules\Orders\Queries\GetOrderDossier;
use App\Modules\Orders\Queries\ListOrders;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Dossier de commande commun (docs/04 §8) : lecture bornée aux parties ; transitions par formulaires serveur. */
class OrderController extends Controller
{
    private const CONSEQUENCES = [
        'accept' => ['Accepter la demande', 'Le client pourra régler la commande. Le paiement n’est pas encore ouvert dans cette version : la commande reste « en attente de paiement ». Aucun travail ne commence et aucune échéance de réalisation ne court tant que le paiement n’est pas confirmé et le brief complet.', 'Accepter la demande'],
        'decline' => ['Refuser la demande', 'La demande est fermée. Le client voit votre motif. Aucun montant n’a été encaissé.', 'Refuser la demande'],
        'withdraw' => ['Retirer la demande', 'La demande est fermée avant toute réponse du freelance. Aucun montant n’a été encaissé.', 'Retirer la demande'],
        'cancel' => ['Annuler la commande', 'La commande est fermée. Aucun montant n’a été encaissé. Vous pourrez refaire une demande plus tard.', 'Annuler la commande'],
    ];

    public function index(Request $request, ListOrders $list): View
    {
        return view('orders.index', ['orders' => $list($request->user(), 'client'), 'space' => 'client']);
    }

    public function show(Request $request, string $reference, GetOrderDossier $dossier): View
    {
        try {
            $d = $dossier($request->user(), $reference);
        } catch (OrderForbidden) {
            abort(404);
        }

        return view('orders.show', ['d' => $d, 'space' => $d->perspective]);
    }

    public function confirm(Request $request, string $reference, string $action, GetOrderDossier $dossier): View|RedirectResponse
    {
        try {
            $d = $dossier($request->user(), $reference);
        } catch (OrderForbidden) {
            abort(404);
        }
        if (! collect($d->actions)->contains('kind', $action)) {
            return redirect()->route('orders.show', $reference)->with('error', 'Cette action n’est plus disponible : la commande a changé.');
        }
        [$title, $text, $button] = self::CONSEQUENCES[$action];

        return view('orders.confirm', ['d' => $d, 'action' => $action, 'title' => $title, 'consequences' => $text, 'button' => $button, 'operationKey' => (string) Str::uuid(), 'space' => $d->perspective]);
    }

    public function act(Request $request, string $reference, string $action, AcceptServiceRequest $accept, DeclineServiceRequest $decline, WithdrawServiceRequest $withdraw, CancelBeforePayment $cancel): RedirectResponse|Response
    {
        $data = $request->validate([
            'expected_version' => ['required', 'integer', 'min:1'],
            'operation_key' => ['required', 'string', 'max:80'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $user = $request->user();
        $version = (int) $data['expected_version'];
        $key = $data['operation_key'];

        try {
            [, $replayed] = match ($action) {
                'accept' => $accept($user, $reference, $version, $key),
                'decline' => $decline($user, $reference, (string) ($data['reason'] ?? ''), $version, $key),
                'withdraw' => $withdraw($user, $reference, $version, $key),
                'cancel' => $cancel($user, $reference, $version, $key),
            };
        } catch (OrderForbidden) {
            abort(404);
        } catch (OrderExpired) {
            return response()->view('errors.order', ['title' => 'Demande expirée', 'message' => 'Le délai de réponse est dépassé : la demande a expiré. Rien n’a été modifié par votre action.', 'back' => route('orders.show', $reference), 'backLabel' => 'Voir la commande'], 409);
        } catch (InvalidTransition) {
            return response()->view('errors.order', ['title' => 'La commande a changé', 'message' => 'La commande a changé pendant que vous la consultiez : cette action n’est plus possible. Rien n’a été modifié.', 'back' => route('orders.show', $reference), 'backLabel' => 'Actualiser la commande'], 409);
        } catch (OperationKeyReused) {
            return response()->view('errors.order', ['title' => 'Envoi déjà traité', 'message' => 'Ce formulaire a déjà été utilisé avec un contenu différent. Rechargez la page.', 'back' => route('orders.show', $reference), 'backLabel' => 'Voir la commande'], 409);
        }

        $messages = ['accept' => 'Demande acceptée. La commande est en attente de paiement ; aucun travail ne commence avant paiement confirmé et brief complet.', 'decline' => 'Demande refusée. Le client en est informé avec votre motif.', 'withdraw' => 'Demande retirée.', 'cancel' => 'Commande annulée. Aucun montant n’a été encaissé.'];

        return redirect()->route('orders.show', $reference)->with('status', $replayed ? 'Cette action avait déjà été enregistrée.' : $messages[$action]);
    }
}
