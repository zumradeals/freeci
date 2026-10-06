<?php

namespace App\Http\Controllers;

use App\Integrations\Payments\GeniusPayConfig;
use App\Integrations\Payments\GeniusPayProvider;
use App\Integrations\Payments\InvalidProviderEvent;
use App\Modules\Finance\Actions\ProcessProviderEvent;
use App\Modules\Finance\Jobs\ProcessPaymentEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Notifications Genius Pay (bac à sable). Sans session ni CSRF : authentifiées par la signature HMAC du CORPS BRUT. Une notification signée
 * est ENREGISTRÉE durablement (dédoublonnée) puis traitée de façon asynchrone ; la réponse 2xx est immédiate (le prestataire exige < 10 s).
 * Aucune indication sur la cause d'un rejet. Désactivée (404) tant que Genius Pay (bac à sable) n'est pas sélectionné et le secret configuré.
 */
class GeniusPayWebhookController extends Controller
{
    private const MAX_BODY = 262144;

    public function __invoke(Request $request, GeniusPayProvider $provider, ProcessProviderEvent $process): JsonResponse
    {
        if (! GeniusPayConfig::sandboxSelected() || blank(config('freeci.payments.genius.webhook_secret'))) {
            abort(404);
        }
        $raw = $request->getContent();
        if (strlen($raw) > self::MAX_BODY) {
            return response()->json(['error' => 'too_large'], 413);
        }
        try {
            $event = $provider->parseEvent($raw, $request->headers->all());
        } catch (InvalidProviderEvent) {
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
