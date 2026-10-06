<?php

namespace App\Console\Commands;

use App\Integrations\Payments\GeniusPayConfig;
use App\Integrations\Payments\PaymentGateways;
use App\Integrations\Payments\PaymentMode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * État de la configuration Genius Pay, par environnement, SANS afficher aucune clé. `--ping` effectue UN appel réel (GET /account) avec les clés de
 * l'environnement actif (ou de `--env`) : échange réel, clés lues côté serveur, aucun paiement créé.
 */
class GeniusStatus extends Command
{
    protected $signature = 'freeci:genius:status {--ping : interroge réellement Genius Pay (GET /account) : échange réel, aucun paiement créé} {--env= : sandbox|live (défaut : mode actif)}';

    protected $description = 'Genius Pay : mode, autorisation, configuration par environnement (clés, secret du webhook, compte marchand), URL du webhook. Aucun secret affiché.';

    public function handle(PaymentGateways $gateways): int
    {
        $yes = fn (bool $b) => $b ? 'OUI' : 'NON';
        $env = $this->option('env') ?: PaymentMode::environment();
        if (! in_array($env, GeniusPayConfig::ENVIRONMENTS, true)) {
            $this->error('--env doit valoir sandbox ou live.');

            return self::FAILURE;
        }
        $this->table(['Contrôle', 'État'], [
            ['Passerelle', 'Genius Pay (unique)'],
            ['Mode configuré (FREECI_PAYMENT_MODE)', (string) config('freeci.payments.mode').(PaymentMode::valid() ? '' : ' — INVALIDE')],
            ['Nouveaux paiements ouverts (FREECI_PAYMENTS_ENABLED)', $yes(PaymentMode::enabled())],
            ['Mode live autorisé (FREECI_LIVE_PAYMENTS_AUTHORIZED)', $yes(GeniusPayConfig::liveAuthorized())],
            ['Création de nouveaux paiements possible', $yes(PaymentMode::creationOpen()).(PaymentMode::blocker() === null ? '' : ' — '.PaymentMode::adminMessage(PaymentMode::blocker()))],
            ['Nouvelles commandes créées comme', PaymentMode::orderEnvironment() === 'live' ? 'LIVE (argent réel)' : 'TEST (aucun argent réel)'],
        ]);
        $rows = [];
        foreach (GeniusPayConfig::ENVIRONMENTS as $e) {
            $p = GeniusPayConfig::problems($e);
            $rows[] = [$e, $yes($p === []), $p === [] ? '—' : implode(', ', $p), $yes(GeniusPayConfig::keys($e)['merchant_id'] !== null)];
        }
        $this->table(['Environnement', 'Configuration conforme', 'Problèmes', 'Compte marchand déclaré'], $rows);
        $this->line('URL du webhook à déclarer (une seule, sandbox ET live) : '.rtrim((string) config('app.url'), '/').'/webhooks/geniuspay');
        $this->line('Événements : payment.success, payment.failed, payment.cancelled (et, facultatifs : payment.initiated, payment.refunded). Un secret `whsec_…` par environnement.');
        $this->line('Tentatives par environnement : '.DB::table('payments')->where('provider', 'genius_pay')->selectRaw('environment, count(*) c')->groupBy('environment')->pluck('c', 'environment')->map(fn ($c, $k) => "$k=$c")->implode(' · ').' · événements à revérifier : '.DB::table('payment_events')->where('processing', 'needs_review')->count().'.');

        if ($this->option('ping')) {
            if (! GeniusPayConfig::ready($env)) {
                $this->error("Configuration « {$env} » incomplète ou non conforme : aucun appel émis.");

                return self::FAILURE;
            }
            $provider = $gateways->forEnvironment($env);
            $id = $provider->merchantId();
            if ($id === null) {
                $this->error('Genius Pay n\'a pas répondu comme attendu (clés refusées, réseau ou URL).');

                return self::FAILURE;
            }
            $this->info('Réponse reçue : compte marchand '.substr($id, 0, 4).'… (identifiant masqué).');
            match ($provider->merchantStatus()) {
                'ok' => $this->info('Compte marchand : conforme à celui déclaré (ou aucun déclaré en sandbox).'),
                'mismatch' => $this->error('Compte marchand DIFFÉRENT de celui déclaré : nouveaux paiements live refusés.'),
                default => $this->warn('Compte marchand non vérifiable.'),
            };

            return $provider->merchantStatus() === 'mismatch' ? self::FAILURE : self::SUCCESS;
        }

        return GeniusPayConfig::ready($env) ? self::SUCCESS : self::FAILURE;
    }
}
