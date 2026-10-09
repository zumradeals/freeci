<?php

namespace App\Http\Controllers;

use App\Integrations\FileScan\FileScanner;
use App\Modules\Catalog\Actions\GetPublishedService;
use App\Modules\Catalog\Actions\SellerSignals;
use App\Modules\Catalog\Data\ServiceDetail;
use App\Modules\Catalog\Exceptions\ServiceNotAvailable;
use App\Modules\Catalog\Exceptions\ServiceNotFound;
use App\Modules\Catalog\Support\ServiceTiers;
use App\Modules\Orders\Actions\RequestService;
use App\Modules\Orders\Exceptions\OperationKeyReused;
use App\Modules\Orders\Exceptions\OrderForbidden;
use App\Modules\Orders\Exceptions\OwnService;
use App\Modules\Orders\Exceptions\PendingRequestExists;
use App\Modules\Orders\Exceptions\RequestsClosed;
use App\Modules\Orders\Exceptions\SellerUnavailable;
use App\Modules\Orders\Exceptions\ServiceChanged;
use App\Shared\Dates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
        if (! app(SellerSignals::class)->for($service->sellerUserId)['available']) {
            return response()->view('errors.order', ['title' => 'Freelance indisponible', 'message' => 'Ce freelance ne prend pas de nouvelle demande pour le moment. Ajoutez le service à vos favoris pour y revenir, ou consultez d’autres services.', 'back' => route('services.show', $slug), 'backLabel' => 'Retour au service'], 409);
        }
        if (! $service->acceptsRequests) {
            return response()->view('errors.order', ['title' => 'Demandes fermées', 'message' => 'Ce service d’exemple n’accepte pas de demande.', 'back' => route('services.show', $slug), 'backLabel' => 'Retour au service'], 409);
        }

        try {
            $selection = $this->selection($service, $request->query('formule'), (array) $request->query('options', []));
        } catch (ValidationException $e) {
            return redirect()->route('services.show', $slug)->with('error', 'Choisissez une formule (et vos options) avant de décrire votre besoin.');
        }

        return view('orders.request', ['selection' => $selection, 'service' => $service, 'operationKey' => (string) Str::uuid(), 'uploadsEnabled' => app(FileScanner::class)->isOperational(), 'changed' => (bool) $request->session()->get('service_changed')]);
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
            'tier' => ['nullable', 'integer', 'min:1', 'max:3'], 'options' => ['nullable', 'array', 'max:5'], 'options.*' => ['integer', 'min:1', 'max:5'],
        ], ['conditions.accepted' => 'Vous devez accepter les conditions de la demande pour l’envoyer.']);

        try {
            [$order, $replayed] = $requestService(
                $request->user(), $slug, (int) $data['service_version'], array_values($data['answers']),
                $data['notes'] ?? null, true, $data['operation_key'], $data['tier'] ?? null, array_values($data['options'] ?? []),
            );
        } catch (ServiceNotFound) {
            abort(404);
        } catch (ServiceNotAvailable) {
            return response()->view('catalog.unavailable', [], 410);
        } catch (OwnService) {
            return response()->view('errors.order', ['title' => 'C’est votre service', 'message' => 'Vous ne pouvez pas commander votre propre service.', 'back' => route('services.show', $slug), 'backLabel' => 'Retour au service'], 403);
        } catch (SellerUnavailable) {
            return response()->view('errors.order', ['title' => 'Freelance indisponible', 'message' => 'Ce freelance ne prend pas de nouvelle demande pour le moment. Ajoutez le service à vos favoris pour y revenir, ou consultez d’autres services.', 'back' => route('services.show', $slug), 'backLabel' => 'Retour au service'], 409);
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
            try {
                $selection = $this->selection($service, $data['tier'] ?? null, array_values($data['options'] ?? []));
            } catch (ValidationException) {
                return redirect()->route('services.show', $slug)->with('error', 'Ce service a été modifié : choisissez de nouveau votre formule et vos options.');
            }

            return response()->view('orders.request', ['selection' => $selection, 'service' => $service, 'operationKey' => (string) Str::uuid(), 'changed' => true, 'uploadsEnabled' => app(FileScanner::class)->isOperational()], 409);
        }

        return redirect()->route('orders.show', $order->reference)->with('status', $replayed
            ? 'Cette demande a déjà été envoyée : la voici.'
            : 'Demande envoyée à '.$order->agreement->seller_name.'. Réponse attendue avant le '.Dates::format($order->response_deadline_at).'.');
    }

    /** Sélection recalculée par le serveur à partir des formules et options publiées ; null pour une offre unique sans option. */
    private function selection(ServiceDetail $service, mixed $tier, array $options): ?array
    {
        if ($service->tiers === [] && $options === []) {
            return null;
        }

        return ServiceTiers::resolve($service->tiers ?: null, $service->options ?: null, $tier, $options, (int) $service->price->xof, $service->deliveryDays, $service->revisionsIncluded);
    }
}
