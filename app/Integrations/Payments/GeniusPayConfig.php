<?php

namespace App\Integrations\Payments;

/**
 * Configuration de Genius Pay, lue côté serveur uniquement. Les clés ne sont jamais journalisées, jamais envoyées au navigateur.
 * Ce lot n'intègre QUE le bac à sable : des clés « live » sont refusées, et aucun réglage ne peut activer le mode réel (autorisation explicite du porteur requise).
 */
final class GeniusPayConfig
{
    public const SANDBOX = 'geniuspay_sandbox';

    public static function sandboxSelected(): bool
    {
        return config('freeci.payments.provider') === self::SANDBOX;
    }

    /** Le mode réel n'est pas autorisé dans cette version. */
    public static function liveAuthorized(): bool
    {
        return false;
    }

    public static function baseUrl(): string
    {
        return rtrim((string) config('freeci.payments.genius.base_url'), '/');
    }

    /** @return list<string> codes de problème de configuration (jamais de valeur secrète) */
    public static function problems(): array
    {
        $c = (array) config('freeci.payments.genius');
        $p = [];
        if (! str_starts_with(strtolower((string) ($c['base_url'] ?? '')), 'https://')) {
            $p[] = 'base_url_not_https';             // aucune clé ne circule en clair
        }
        $key = (string) ($c['api_key'] ?? '');
        $secret = (string) ($c['api_secret'] ?? '');
        if ($key === '' || $secret === '') {
            $p[] = 'keys_missing';
        } elseif (str_starts_with($key, 'pk_live_') || str_starts_with($secret, 'sk_live_')) {
            $p[] = 'live_keys_refused';
        } elseif (! str_starts_with($key, 'pk_sandbox_') || ! str_starts_with($secret, 'sk_sandbox_')) {
            $p[] = 'keys_not_sandbox';
        }
        if ((string) ($c['webhook_secret'] ?? '') === '') {
            $p[] = 'webhook_secret_missing';
        }

        return $p;
    }

    public static function ready(): bool
    {
        return self::sandboxSelected() && self::problems() === [];
    }

    /** @return list<string> */
    public static function checkoutHosts(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', strtolower((string) config('freeci.payments.genius.checkout_hosts'))))));
    }
}
