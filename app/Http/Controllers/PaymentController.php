<?php

namespace App\Http\Controllers;

use App\Modules\Finance\Actions\InitiatePayment;
use App\Modules\Finance\Actions\RefreshPaymentStatus;
use App\Modules\Finance\Exceptions\PaymentAlreadyConfirmed;
use App\Modules\Finance\Exceptions\PaymentInProgress;
use App\Modules\Finance\Exceptions\PaymentNotAvailable;
use App\Modules\Finance\Queries\GetPaymentPage;
use App\Modules\Orders\Exceptions\InvalidTransition;
use App\Modules\Orders\Exceptions\OrderExpired;
use App\Modules\Orders\Exceptions\OrderForbidden;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Page de paiement SIMULÉ (docs/04 §9). L'état affiché est LU EN BASE : aucun paramètre d'URL ni retour de navigateur
 * ne confirme quoi que ce soit. Aucune action ici ne peut marquer un paiement comme confirmé.
 */
class PaymentController extends Controller
{
    public function show(Request $request, string $reference, GetPaymentPage $page): View
    {
        try {
            $p = $page($request->user(), $reference);
        } catch (OrderForbidden) {
            abort(404);
        }

        return view('orders.payment', ['p' => $p, 'space' => 'client', 'operationKey' => (string) Str::uuid()]);
    }

    public function pay(Request $request, string $reference, InitiatePayment $initiate): RedirectResponse|Response
    {
        $data = $request->validate(['operation_key' => ['required', 'string', 'max:80'], 'conditions' => ['accepted']], ['conditions.accepted' => 'Confirmez que vous avez compris qu’il s’agit d’un paiement simulé.']);

        try {
            [, $replayed] = $initiate($request->user(), $reference, $data['operation_key']);
        } catch (OrderForbidden) {
            abort(404);
        } catch (PaymentNotAvailable) {
            return $this->problem('Paiement non disponible', 'Le paiement simulé n’est pas disponible pour cette commande. Aucun paiement n’est possible ici.', $reference);
        } catch (PaymentInProgress) {
            return $this->problem('Paiement déjà en cours', 'Une tentative de paiement est déjà en cours ou en vérification : ne payez pas une seconde fois. Consultez son état.', $reference);
        } catch (PaymentAlreadyConfirmed) {
            return $this->problem('Paiement déjà confirmé', 'Le paiement de cette commande est déjà confirmé.', $reference);
        } catch (OrderExpired) {
            return $this->problem('Commande expirée', 'Le délai de paiement est dépassé : la commande a expiré.', $reference);
        } catch (InvalidTransition) {
            return $this->problem('La commande a changé', 'La commande a changé : le paiement n’est plus possible. Rien n’a été débité.', $reference);
        }

        return redirect()->route('orders.payment', $reference)->with('status', $replayed ? 'Cette tentative était déjà enregistrée.' : 'Paiement simulé démarré. Le résultat est vérifié côté serveur.');
    }

    public function refresh(Request $request, string $reference, RefreshPaymentStatus $refresh): RedirectResponse
    {
        try {
            [, $limited] = $refresh($request->user(), $reference);
        } catch (OrderForbidden) {
            abort(404);
        }

        return redirect()->route('orders.payment', $reference)->with($limited ? 'error' : 'status', $limited ? 'Actualisation trop fréquente : patientez quelques secondes.' : 'Statut vérifié auprès du prestataire simulé.');
    }

    private function problem(string $title, string $message, string $reference): Response
    {
        return response()->view('errors.order', ['title' => $title, 'message' => $message, 'back' => route('orders.show', $reference), 'backLabel' => 'Voir la commande'], 409);
    }
}
