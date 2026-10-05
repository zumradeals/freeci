<?php

namespace App\Http\Controllers;

use App\Integrations\Payments\InvalidProviderEvent;
use App\Integrations\Payments\PaymentProvider;
use App\Modules\Finance\Actions\ProcessProviderEvent;
use App\Modules\Finance\SandboxGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Notifications du prestataire SIMULÉ (sans session, sans CSRF : authentifiées par signature HMAC horodatée).
 * Désactivée (404) tant que le simulateur n'est pas activé. Ce n'est pas un bouton : seul le détenteur du secret peut l'appeler.
 */
class SandboxWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentProvider $provider, ProcessProviderEvent $process): JsonResponse
    {
        if (! SandboxGate::enabled() || blank(config('freeci.payments.sandbox_webhook_secret'))) {
            abort(404);
        }
        try {
            $event = $provider->parseEvent($request->getContent(), $request->headers->all());
        } catch (InvalidProviderEvent) {
            return response()->json(['error' => 'invalid_event'], 400);   // aucune indication sur la cause
        }

        return response()->json(['outcome' => $process($event)]);
    }
}
