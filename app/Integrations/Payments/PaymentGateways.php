<?php

namespace App\Integrations\Payments;

/** Accès à la passerelle : celle des NOUVEAUX paiements (mode configuré) ou celle d'une tentative existante (son environnement enregistré, jamais le mode courant). */
class PaymentGateways
{
    public function forEnvironment(string $environment): GeniusPayProvider
    {
        return app(GeniusPayProvider::class, ['environment' => $environment === 'live' ? 'live' : 'sandbox']);
    }

    public function active(): PaymentProvider
    {
        return $this->forEnvironment(PaymentMode::environment());
    }

    /**
     * Notification entrante : l'environnement n'est JAMAIS cru sur l'en-tête (non authentifié). Il ne sert qu'à choisir le secret à essayer ;
     * la signature doit correspondre au secret de CET environnement et le corps annoncer le même environnement.
     *
     * @throws InvalidProviderEvent
     */
    public function parseWebhook(string $rawBody, array $headers): ProviderEvent
    {
        $h = strtolower(trim((string) (is_array($headers['x-webhook-environment'] ?? null) ? ($headers['x-webhook-environment'][0] ?? '') : ($headers['x-webhook-environment'] ?? ''))));
        $candidates = in_array($h, GeniusPayConfig::ENVIRONMENTS, true) ? [$h] : GeniusPayConfig::ENVIRONMENTS;
        $badBody = null;
        foreach ($candidates as $env) {
            if (GeniusPayConfig::keys($env)['webhook_secret'] === '') {
                continue;
            }
            try {
                return $this->forEnvironment($env)->parseEvent($rawBody, $headers);
            } catch (InvalidProviderBody $e) {
                $badBody = $e;          // signature valide pour cet environnement, corps inexploitable : on le signale tel quel (400)
            } catch (InvalidProviderEvent) {
                continue;
            }
        }
        throw $badBody ?? new InvalidProviderEvent('Notification non authentifiée.');
    }

    /** Au moins un secret de webhook est-il configuré ? (sinon l'adresse répond 404). */
    public function webhookConfigured(): bool
    {
        foreach (GeniusPayConfig::ENVIRONMENTS as $env) {
            if (GeniusPayConfig::keys($env)['webhook_secret'] !== '') {
                return true;
            }
        }

        return false;
    }
}
