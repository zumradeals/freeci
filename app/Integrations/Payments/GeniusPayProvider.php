<?php

namespace App\Integrations\Payments;

use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Adaptateur Genius Pay, lié à UN environnement (sandbox | live) : clés, secret de webhook et compte marchand de cet environnement uniquement.
 * Le mode live est développé mais n'est utilisable que si le porteur l'a autorisé explicitement dans la configuration serveur. Checkout hébergé : on crée le paiement côté serveur,
 * le navigateur est redirigé vers le checkout, et SEUL le serveur confirme (webhook signé + revérification par l'API).
 * Ne journalise jamais de clé, de secret, ni de corps de réponse. Aucune redirection HTTP suivie (les clés ne partent jamais ailleurs).
 */
class GeniusPayProvider implements PaymentProvider
{
    public const NAME = 'genius_pay';

    public function __construct(private readonly string $environment = 'sandbox') {}

    public function name(): string
    {
        return self::NAME;
    }

    public function environment(): string
    {
        return $this->environment === 'live' ? 'live' : 'sandbox';
    }

    public function createCheckout(CheckoutRequest $request): CheckoutResult
    {
        $body = array_filter([
            'amount' => $request->amountXof,                       // XOF entier, lu dans l'accord figé par l'appelant
            'currency' => $request->currency,
            'description' => $request->description === null ? null : mb_substr($request->description, 0, 500),
            'external_reference' => $request->providerReference,    // stable par tentative ; sert aussi de clé d'idempotence (doc)
            'success_url' => $request->successUrl,
            'error_url' => $request->errorUrl,
            'metadata' => ['attempt' => $request->providerReference, 'order_reference' => $request->orderReference, 'environment' => $this->environment()],
        ], fn ($v) => $v !== null);

        $r = $this->send(fn (PendingRequest $h) => $h->withHeaders(['Idempotency-Key' => $request->providerReference])->post('/payments', $body), creating: true);
        $this->assertDefinite($r);
        $d = $r->json('data');
        if (! in_array($r->status(), [200, 201], true) || $r->json('success') !== true || ! is_array($d)) {
            throw new \RuntimeException('Réponse de création non exploitable.');                     // incertain : on ne conclut pas
        }
        $env = $d['environment'] ?? null;
        if ($env !== null && $env !== $this->environment()) {
            throw new ProviderRejected('environment_mismatch');
        }
        if (! isset($d['amount']) || $this->xof($d['amount']) !== $request->amountXof) {
            throw new ProviderRejected('amount_mismatch');
        }
        $ref = $d['reference'] ?? null;
        if (! is_string($ref) || $ref === '' || strlen($ref) > 80) {
            throw new \RuntimeException('Référence du prestataire absente.');
        }
        $url = $d['checkout_url'] ?? null;

        return new CheckoutResult(
            $request->providerReference, is_string($url) && $this->allowedCheckout($url) ? $url : null, $ref, isset($d['id']) ? (string) $d['id'] : null,
            isset($d['expires_at']) && is_string($d['expires_at']) ? Carbon::parse($d['expires_at'])->toIso8601String() : null,
            ($d['external_reference'] ?? null) === $request->providerReference, $env ?? $this->environment(),
        );
    }

    /** `$reference` : référence attribuée par le prestataire. Résultat incertain (réseau, 5xx) → Indeterminate, jamais Succeeded. */
    public function verify(string $reference): Verification
    {
        try {
            $r = $this->send(fn (PendingRequest $h) => $h->get('/payments/'.rawurlencode($reference)));
        } catch (Throwable) {
            return new Verification(ProviderStatus::Indeterminate);
        }
        if ($r->status() === 404) {
            return new Verification(ProviderStatus::NotFound);
        }
        $d = $r->json('data');
        if (! $r->successful() || $r->json('success') !== true || ! is_array($d) || ! is_string($d['reference'] ?? null)) {
            return new Verification(ProviderStatus::Indeterminate);
        }
        $status = match ((string) ($d['status'] ?? '')) {
            'completed' => ProviderStatus::Succeeded,
            'pending', 'processing' => ProviderStatus::Pending,
            'failed', 'cancelled' => ProviderStatus::Failed,
            'refunded' => ProviderStatus::Refunded,
            default => ProviderStatus::Indeterminate,
        };

        return new Verification(
            $status, isset($d['amount']) ? $this->xof($d['amount']) : null, isset($d['currency']) && is_string($d['currency']) ? strtoupper($d['currency']) : null,
            null, $d['reference'], is_string($d['environment'] ?? null) ? $d['environment'] : $this->environment(),   // à défaut : l'environnement de la clé utilisée
        );
    }

    /** Identifiant du compte marchand lié aux clés de cet environnement (source fiable côté serveur), mis en cache une heure. */
    public function merchantId(): ?string
    {
        return Cache::remember('geniuspay:merchant:'.$this->environment(), 3600, function () {
            try {
                $r = $this->send(fn (PendingRequest $h) => $h->get('/account'));
            } catch (Throwable) {
                return null;
            }
            $id = $r->successful() ? $r->json('data.id') : null;

            return is_string($id) && $id !== '' ? $id : null;
        });
    }

    /**
     * ok = compte de l'API identique au compte déclaré (obligatoire en live) ; mismatch = identifiants différents ; unavailable = non vérifiable.
     * En sandbox, un compte non déclaré n'est pas bloquant (ok si l'API répond) ; en live, l'absence de déclaration est un problème de configuration.
     */
    public function merchantStatus(): string
    {
        $api = $this->merchantId();
        if ($api === null) {
            return 'unavailable';
        }
        $expected = GeniusPayConfig::keys($this->environment())['merchant_id'];

        return $expected === null || hash_equals($expected, $api) ? 'ok' : 'mismatch';
    }

    /** @throws InvalidProviderEvent */
    public function parseEvent(string $rawBody, array $headers): ProviderEvent
    {
        $secret = GeniusPayConfig::keys($this->environment())['webhook_secret'];
        if ($secret === '') {
            throw new InvalidProviderEvent('Secret de notification non configuré.');
        }
        $h = fn (string $n) => (string) (is_array($headers[$n] ?? null) ? ($headers[$n][0] ?? '') : ($headers[$n] ?? ''));
        $signature = strtolower(trim($h('x-webhook-signature')));
        $ts = trim($h('x-webhook-timestamp'));
        if (! preg_match('/^[0-9a-f]{64}$/', $signature) || ! ctype_digit($ts) || strlen($ts) > 12) {
            throw new InvalidProviderEvent('Signature absente ou mal formée.');
        }
        // Signature calculée sur le CORPS BRUT (jamais re-sérialisé) : HMAC-SHA256 de « timestamp.corps ».
        if (! hash_equals(hash_hmac('sha256', $ts.'.'.$rawBody, $secret), $signature)) {
            throw new InvalidProviderEvent('Signature invalide.');
        }
        // Fraîcheur : les reprises documentées vont jusqu'à 24 h ; la fenêtre en tient compte (défaut 25 h). Les rejeux dans la fenêtre sont sans effet
        // (événement dédoublonné, effet idempotent, succès toujours revérifié par l'API). Un horodatage du FUTUR est refusé.
        $age = time() - (int) $ts;
        if ($age < -300 || $age > (int) config('freeci.payments.genius.webhook_tolerance')) {
            throw new InvalidProviderEvent('Horodatage hors tolérance.');
        }
        $envHeader = strtolower(trim($h('x-webhook-environment')));
        $data = json_decode($rawBody, true);
        $tx = is_array($data) ? ($data['data']['transaction'] ?? null) : null;
        if (! is_array($data) || ! is_string($data['event'] ?? null) || ! is_array($tx) || ! is_string($tx['reference'] ?? null) || $tx['reference'] === '' || strlen($tx['reference']) > 80) {
            throw new InvalidProviderEvent('Corps de notification invalide.');
        }
        $env = strtolower((string) ($data['data']['environment'] ?? ''));
        if ($env === '' || ($envHeader !== '' && $envHeader !== $env) || $env !== $this->environment()) {
            throw new InvalidProviderEvent('Environnement absent ou incohérent.');   // un secret ne valide que les notifications de SON environnement
        }
        $status = match ($data['event']) {
            'payment.success' => ProviderStatus::Succeeded,
            'payment.failed', 'payment.cancelled' => ProviderStatus::Failed,
            'payment.initiated' => ProviderStatus::Pending,
            'payment.refunded' => ProviderStatus::Refunded,
            default => ProviderStatus::Other,
        };
        // Aucun identifiant d'événement n'est documenté : clé dérivée (événement + transaction + horodatage du corps) ; le corps entier est empreinté.
        $eventId = hash('sha256', $data['event'].'|'.$tx['reference'].'|'.($data['timestamp'] ?? $ts));

        return new ProviderEvent(self::NAME, $eventId, $tx['reference'], $status, [
            'event' => $data['event'], 'environment' => $env, 'merchant_id' => is_string($data['data']['merchant']['id'] ?? null) ? $data['data']['merchant']['id'] : null,
            'transaction' => ['reference' => $tx['reference'], 'id' => isset($tx['id']) ? (string) $tx['id'] : null, 'status' => is_string($tx['status'] ?? null) ? $tx['status'] : null,
                'amount' => isset($tx['amount']) ? $this->xof($tx['amount']) : null, 'attempt' => is_array($tx['metadata'] ?? null) && is_string($tx['metadata']['attempt'] ?? null) ? $tx['metadata']['attempt'] : null],
            'signature_timestamp' => (int) $ts, 'body_sha256' => hash('sha256', $rawBody),          // pas de données client conservées
        ]);
    }

    // ---------------------------------------------------------------------------------------------

    /** @param callable(PendingRequest): Response $do */
    private function send(callable $do, bool $creating = false): Response
    {
        if (GeniusPayConfig::problems($this->environment()) !== []) {
            throw new ProviderRejected('configuration_invalid');                    // aucun appel n'est émis, aucune clé ne circule
        }
        if ($creating && $this->environment() === 'live' && ! GeniusPayConfig::liveAuthorized()) {
            throw new ProviderRejected('live_not_authorized');                      // aucune CRÉATION live sans autorisation explicite ; la lecture des tentatives existantes reste possible
        }
        $k = GeniusPayConfig::keys($this->environment());
        $h = Http::baseUrl(GeniusPayConfig::baseUrl())->acceptJson()->asJson()
            ->withHeaders(['X-API-Key' => $k['api_key'], 'X-API-Secret' => $k['api_secret']])
            ->connectTimeout(5)->timeout((int) config('freeci.payments.genius.timeout'))->withOptions(['allow_redirects' => false]);
        try {
            return $do($h);
        } catch (ConnectionException $e) {
            throw new \RuntimeException('Prestataire injoignable ou délai dépassé.');          // incertain ; aucun détail (le message pourrait contenir l'URL)
        }
    }

    /** Refus définitifs (aucune transaction créée) ; 5xx / 429 / réponse illisible restent « incertains ». */
    private function assertDefinite(Response $r): void
    {
        $s = $r->status();
        if ($s === 401 || $s === 403) {
            throw new ProviderRejected('provider_auth');
        }
        if (in_array($s, [400, 404, 422], true) || ($s >= 300 && $s < 400)) {
            throw new ProviderRejected($s === 422 || $s === 400 ? 'provider_validation' : 'provider_unexpected');
        }
        if ($s === 429 || $s >= 500) {
            throw new \RuntimeException('Prestataire indisponible.');
        }
    }

    private function allowedCheckout(string $url): bool
    {
        $p = parse_url($url);

        return is_array($p) && ($p['scheme'] ?? '') === 'https' && ! isset($p['user']) && in_array(strtolower((string) ($p['host'] ?? '')), GeniusPayConfig::checkoutHosts(), true);
    }

    private function xof(mixed $v): ?int
    {
        return is_int($v) || (is_numeric($v) && (float) $v == (int) $v) ? (int) $v : null;
    }
}
