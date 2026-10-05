<?php

namespace App\Integrations\Payments;

use Illuminate\Support\Facades\DB;

/**
 * Prestataire SIMULÉ. Ses « transactions » vivent dans `sandbox_transactions` ; seul un opérateur (console) ou une
 * notification signée peut en changer l'issue. Aucune route publique ne confirme quoi que ce soit.
 */
class SandboxPaymentProvider implements PaymentProvider
{
    public const NAME = 'sandbox';

    public function name(): string
    {
        return self::NAME;
    }

    public function createCheckout(CheckoutRequest $request): CheckoutResult
    {
        DB::table('sandbox_transactions')->insertOrIgnore([
            'reference' => $request->providerReference, 'amount_xof' => $request->amountXof, 'currency' => $request->currency,
            'status' => ProviderStatus::Pending->value, 'order_reference' => $request->orderReference,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return new CheckoutResult($request->providerReference, null);
    }

    public function verify(string $reference): Verification
    {
        $tx = DB::table('sandbox_transactions')->where('reference', $reference)->first();
        if ($tx === null) {
            return new Verification(ProviderStatus::NotFound);
        }

        return new Verification(ProviderStatus::from($tx->status), (int) $tx->amount_xof, $tx->currency, $tx->order_reference);
    }

    public function parseEvent(string $rawBody, array $headers): ProviderEvent
    {
        $secret = (string) config('freeci.payments.sandbox_webhook_secret');
        if ($secret === '') {
            throw new InvalidProviderEvent('Secret de notification non configuré.');
        }
        $header = (string) ($headers['x-sandbox-signature'][0] ?? $headers['x-sandbox-signature'] ?? '');
        if (! preg_match('/^t=(\d+),v1=([0-9a-f]{64})$/', $header, $m)) {
            throw new InvalidProviderEvent('Signature absente ou mal formée.');
        }
        if (abs(time() - (int) $m[1]) > 300) {
            throw new InvalidProviderEvent('Horodatage hors tolérance.');
        }
        if (! hash_equals(self::sign($secret, (int) $m[1], $rawBody), $m[2])) {
            throw new InvalidProviderEvent('Signature invalide.');
        }

        $data = json_decode($rawBody, true);
        if (! is_array($data) || ! is_string($data['id'] ?? null) || ! is_string($data['reference'] ?? null)
            || ! is_string($data['status'] ?? null) || ProviderStatus::tryFrom($data['status']) === null
            || in_array($data['status'], ['not_found'], true)) {
            throw new InvalidProviderEvent('Corps de notification invalide.');
        }

        return new ProviderEvent(self::NAME, mb_substr($data['id'], 0, 120), $data['reference'], ProviderStatus::from($data['status']), $data);
    }

    public static function sign(string $secret, int $timestamp, string $body): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    public static function signatureHeader(string $secret, string $body, ?int $timestamp = null): string
    {
        $timestamp ??= time();

        return 't='.$timestamp.',v1='.self::sign($secret, $timestamp, $body);
    }
}
