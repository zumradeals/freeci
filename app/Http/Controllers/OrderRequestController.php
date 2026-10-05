<?php

namespace App\Http\Controllers;

use App\Modules\Catalog\Actions\GetPublishedService;
use App\Modules\Catalog\Exceptions\ServiceNotAvailable;
use App\Modules\Catalog\Exceptions\ServiceNotFound;
use App\Modules\Orders\Actions\RequestService;
use App\Modules\Orders\Exceptions\OperationKeyReused;
use App\Modules\Orders\Exceptions\OrderForbidden;
use App\Modules\Orders\Exceptions\OwnService;
use App\Modules\Orders\Exceptions\PendingRequestExists;
use App\Modules\Orders\Exceptions\RequestsClosed;
use App\Modules\Orders\Exceptions\ServiceChanged;
use App\Shared\Dates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Demande de prestation (docs/04 §3) : page dédiée, formulaire serveur, fonctionne sans JavaScript. */
class OrderRequestController extends Controller
{
    public function create(Request $request, string $slug, GetPublishedService $get): View|Response|RedirectResponse
    {
        try {
            $service = $get($slug);
        } catch (ServiceNotFound) {
            abort(404);
        } catch (ServiceNotAvailable) {
            return response()->view('catalog.unavailable', [], 410);
        }
        if ($service->sellerUserId === $request->user()->getKey()) {
            return response()->view('errors.order', ['title' => 'C’est votre service', 'message' => 'Vous ne pouvez pas commander votre propre service.', 'back' => route('services.show', $slug), 'backLabel' => 'Retour au service'], 403);
        }
        if (! $service->acceptsRequests) {
            return response()->view('errors.order', ['title' => 'Demandes fermées', 'message' => 'Ce service d’exemple n’accepte pas de demande.', 'back' => route('services.show', $slug), 'backLabel' => 'Retour au service'], 409);
        }

        return view('orders.request', ['service' => $service, 'operationKey' => (string) Str::uuid(), 'changed' => (bool) $request->session()->get('service_changed')]);
    }

    public function store(Request $request, string $slug, RequestService $requestService, GetPublishedService $get): RedirectResponse|Response
    {
        $data = $request->validate([
            'service_version' => ['required', 'integer', 'min:1'],
            'operation_key' => ['required', 'string', 'max:80'],
            'answers' => ['required', 'array', 'max:20'],
            'answers.*' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'conditions' => ['accepted'],
        ], ['conditions.accepted' => 'Vous devez accepter les conditions de la demande pour l’envoyer.']);

        try {
            [$order, $replayed] = $requestService(
                $request->user(), $slug, (int) $data['service_version'], array_values($data['answers']),
                $data['notes'] ?? null, true, $data['operation_key'],
            );
        } catch (ServiceNotFound) {
            abort(404);
        } catch (ServiceNotAvailable) {
            return response()->view('catalog.unavailable', [], 410);
        } catch (OwnService) {
            return response()->view('errors.order', ['title' => 'C’est votre service', 'message' => 'Vous ne pouvez pas commander votre propre service.', 'back' => route('services.show', $slug), 'backLabel' => 'Retour au service'], 403);
        } catch (RequestsClosed) {
            return response()->view('errors.order', ['title' => 'Demandes fermées', 'message' => 'Ce service n’accepte pas de demande.', 'back' => route('services.show', $slug), 'backLabel' => 'Retour au service'], 409);
        } catch (OrderForbidden) {
            abort(403);
        } catch (PendingRequestExists) {
            return response()->view('errors.order', ['title' => 'Demande déjà envoyée', 'message' => 'Vous avez déjà une demande en attente de réponse pour ce service. Retrouvez-la dans vos commandes.', 'back' => route('orders.index'), 'backLabel' => 'Voir mes commandes'], 409);
        } catch (OperationKeyReused) {
            return response()->view('errors.order', ['title' => 'Envoi déjà traité', 'message' => 'Ce formulaire a déjà été utilisé avec un contenu différent. Rechargez la page pour recommencer.', 'back' => route('services.request', $slug), 'backLabel' => 'Recommencer'], 409);
        } catch (ServiceChanged) {
            // 409 : on rend à nouveau le formulaire avec les conditions à jour, brouillon conservé (docs/04 §3.5).
            $service = $get($slug);

            return response()->view('orders.request', ['service' => $service, 'operationKey' => (string) Str::uuid(), 'changed' => true], 409);
        }

        return redirect()->route('orders.show', $order->reference)->with('status', $replayed
            ? 'Cette demande a déjà été envoyée : la voici.'
            : 'Demande envoyée à '.$order->agreement->seller_name.'. Réponse attendue avant le '.Dates::format($order->response_deadline_at).'.');
    }
}
