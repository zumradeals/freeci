<?php

namespace App\Integrations\Payments;

/**
 * Réglages de paiement lus côté serveur, SANS aucun basculement automatique :
 *  - prestataire : Genius Pay (unique) ;
 *  - environnement des NOUVELLES commandes et NOUVEAUX paiements : « sandbox » (commandes de TEST, aucun argent réel) ou « live » ;
 *  - autorisation de créer de nouveaux paiements (`FREECI_PAYMENTS_ENABLED`) : sans configuration valide, ils sont désactivés.
 * Une valeur de mode inconnue n'est JAMAIS interprétée comme « live » : les nouvelles commandes restent des commandes de test et aucun paiement n'est créé.
 */
final class PaymentMode
{
    public const SANDBOX = 'sandbox';

    public const LIVE = 'live';

    public static function valid(): bool
    {
        return in_array(config('freeci.payments.mode'), [self::SANDBOX, self::LIVE], true);
    }

    /** Environnement de la passerelle pour les nouveaux paiements. */
    public static function environment(): string
    {
        return self::valid() && config('freeci.payments.mode') === self::LIVE ? self::LIVE : self::SANDBOX;
    }

    public static function isLive(): bool
    {
        return self::environment() === self::LIVE;
    }

    /** Environnement (« test » | « live ») inscrit sur toute NOUVELLE commande, dès sa création. */
    public static function orderEnvironment(): string
    {
        return self::isLive() ? 'live' : 'test';
    }

    public static function enabled(): bool
    {
        return (bool) config('freeci.payments.enabled');
    }

    /** Code du premier obstacle à la création d'un paiement, ou null si les NOUVEAUX paiements sont possibles. */
    public static function blocker(): ?string
    {
        if (! self::valid()) {
            return 'mode_invalid';
        }
        if (! self::enabled()) {
            return 'payments_disabled';
        }
        if (GeniusPayConfig::problems(self::environment()) !== []) {
            return 'config_invalid';
        }
        if (self::isLive() && ! GeniusPayConfig::liveAuthorized()) {
            return 'live_not_authorized';
        }

        return null;
    }

    public static function creationOpen(): bool
    {
        return self::blocker() === null;
    }

    /** Message destiné à l'utilisateur : compréhensible, sans détail de configuration. */
    public static function userMessage(?string $code): string
    {
        return $code === null ? '' : 'Les paiements ne sont pas ouverts pour le moment. Votre commande est conservée : revenez plus tard. Rien n’a été débité.';
    }

    /** Message destiné à l'exploitation (administrateurs, console). */
    public static function adminMessage(?string $code): string
    {
        return match ($code) {
            null => 'Les nouveaux paiements sont possibles.',
            'mode_invalid' => 'FREECI_PAYMENT_MODE doit valoir « sandbox » ou « live » : nouveaux paiements désactivés.',
            'payments_disabled' => 'FREECI_PAYMENTS_ENABLED n’est pas à « true » : nouveaux paiements désactivés (le suivi des tentatives existantes continue).',
            'config_invalid' => 'Configuration Genius Pay incomplète ou incohérente pour l’environnement « '.self::environment().' » : '.implode(', ', GeniusPayConfig::problems(self::environment())).'.',
            'live_not_authorized' => 'Mode live sélectionné sans FREECI_LIVE_PAYMENTS_AUTHORIZED=true : nouveaux paiements désactivés.',
            default => $code,
        };
    }
}
