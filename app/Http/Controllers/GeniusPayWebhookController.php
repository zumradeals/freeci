<?php

namespace App\Http\Controllers;

use App\Integrations\Payments\GeniusPayConfig;
use App\Integrations\Payments\InvalidProviderBody;
use App\Integrations\Payments\InvalidProviderEvent;
use App\Integrations\Payments\PaymentGateways;
use App\Modules\Finance\Actions\ProcessProviderEvent;
use App\Modules\Finance\Jobs\ProcessPaymentEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Notifications Genius Pay (sandbox ET live, une seule adresse). Sans session ni CSRF : authentifiées par la signature HMAC du CORPS BRUT. Une notification signée
 * est ENREGISTRÉE durablement (dédoublonnée) puis traitée de façon asynchrone ; la réponse 2xx est immédiate (le prestataire exige < 10 s).
 * Aucune indication sur la cause d'un rejet. Désactivée (404) tant qu'aucun secret de webhook n'est configuré. Le secret essayé dépend de l'environnement annoncé ; la signature doit correspondre au secret de CET environnement.
La réception ne dépend PAS de l'ouverture des nouveaux paiements : le suivi des tentatives existantes continue.
 */
class GeniusPayWebhookController extends Controller
{
    private const MAX_BODY = 262144;

    public function __invoke(Request $request, PaymentGateways $gateways, ProcessProviderEvent $process): JsonResponse
    {
        if (! $gateways->webhookConfigured()) {
            abort(404);
        }
        $raw = $request->getContent();
        if (strlen($raw) > self::MAX_BODY) {
            return response()->json(['error' => 'too_large'], 413);
        }
        try {
            $event = $gateways->parseWebhook($raw, $request->headers->all());
        } catch (InvalidProviderBody) {
            Log::warning('geniuspay.webhook_body_invalid', ['length' => strlen($raw)]);       // signature valide, corps inexploitable : ni secret en cause, ni rejeu utile

            return response()->json(['error' => 'invalid_body'], 400);
        } catch (InvalidProviderEvent) {
            // Trace de diagnostic SANS donnée sensible (booléens et longueurs seulement) ; aucun enregistrement durable en base.
            foreach (GeniusPayConfig::ENVIRONMENTS as $env) {
                if (GeniusPayConfig::keys($env)['webhook_secret'] !== '') {
                    Log::warning('geniuspay.webhook_rejected', $gateways->forEnvironment($env)->diagnose($raw, $request->headers->all()));
                }
            }

            return response()->json(['error' => 'invalid_signature'], 401);
        }

        [$id, $duplicate] = $process->record($event);
        if ($id !== null) {
            try {
                ProcessPaymentEvent::dispatch($id);
            } catch (\Throwable $e) {
                report($e);          // l'événement est enregistré : la tâche de rapprochement le traitera
            }
        }

        return response()->json(['received' => true, 'duplicate' => $duplicate]);
    }
}
