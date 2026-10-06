<?php

namespace App\Integrations\Payments;

/**
 * Configuration de Genius Pay PAR ENVIRONNEMENT (sandbox | live), lue côté serveur uniquement. Les clés ne sont jamais journalisées ni envoyées au navigateur.
 * Les deux jeux de clés sont indépendants : une clé d'un environnement placée dans l'autre est refusée (aucun appel n'est émis).
 */
final class GeniusPayConfig
{
    public const ENVIRONMENTS = ['sandbox', 'live'];

    /** Le mode live n'est utilisable que si le porteur l'a explicitement autorisé dans la configuration serveur. */
    public static function liveAuthorized(): bool
    {
        return (bool) config('freeci.payments.live_authorized');
    }

    public static function baseUrl(): string
    {
        return rtrim((string) config('freeci.payments.genius.base_url'), '/');
    }

    /** @return array{api_key: string, api_secret: string, webhook_secret: string, merchant_id: ?string} */
    public static function keys(string $environment): array
    {
        $c = (array) config('freeci.payments.genius.'.($environment === 'live' ? 'live' : 'sandbox'));

        return [
            'api_key' => (string) ($c['api_key'] ?? ''), 'api_secret' => (string) ($c['api_secret'] ?? ''), 'webhook_secret' => (string) ($c['webhook_secret'] ?? ''),
            'merchant_id' => ($c['merchant_id'] ?? '') === '' ? null : (string) $c['merchant_id'],
        ];
    }

    /** @return list<string> codes de problème de configuration pour cet environnement (jamais de valeur secrète) */
    public static function problems(string $environment): array
    {
        $k = self::keys($environment);
        $p = [];
        if (! str_starts_with(strtolower((string) config('freeci.payments.genius.base_url')), 'https://')) {
            $p[] = 'base_url_not_https';             // aucune clé ne circule en clair
        }
        $pk = $environment === 'live' ? 'pk_live_' : 'pk_sandbox_';
        $sk = $environment === 'live' ? 'sk_live_' : 'sk_sandbox_';
        if ($k['api_key'] === '' || $k['api_secret'] === '') {
            $p[] = 'keys_missing';
        } elseif (! str_starts_with($k['api_key'], $pk) || ! str_starts_with($k['api_secret'], $sk)) {
            $p[] = 'keys_wrong_environment';         // ex. clé « live » dans le jeu sandbox, ou l'inverse
        }
        if ($k['webhook_secret'] === '') {
            $p[] = 'webhook_secret_missing';
        } elseif (! str_starts_with($k['webhook_secret'], 'whsec_')) {
            $p[] = 'webhook_secret_format';          // la documentation indique des secrets « whsec_… »
        }
        if ($environment === 'live' && $k['merchant_id'] === null) {
            $p[] = 'merchant_id_missing';            // en live, le compte marchand attendu doit être déclaré explicitement
        }

        return $p;
    }

    public static function ready(string $environment): bool
    {
        return self::problems($environment) === [];
    }

    /** @return list<string> */
    public static function checkoutHosts(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', strtolower((string) config('freeci.payments.genius.checkout_hosts'))))));
    }
}
