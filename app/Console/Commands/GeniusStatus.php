<?php

namespace App\Console\Commands;

use App\Integrations\Payments\GeniusPayConfig;
use App\Integrations\Payments\GeniusPayProvider;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** État de la configuration Genius Pay (bac à sable) SANS afficher aucune clé. `--ping` effectue UN appel réel (GET /account) avec les clés configurées. */
class GeniusStatus extends Command
{
    protected $signature = 'freeci:genius:status {--ping : interroge réellement Genius Pay (GET /account) : échange réel, clés lues côté serveur}';

    protected $description = 'Configuration Genius Pay (bac à sable) : prestataire actif, clés présentes et conformes, secret du webhook, URL du webhook. Aucun secret affiché.';

    public function handle(GeniusPayProvider $provider): int
    {
        $problems = GeniusPayConfig::problems();
        $yes = fn (bool $b) => $b ? 'OUI' : 'NON';
        $this->table(['Contrôle', 'État'], [
            ['Prestataire des nouvelles tentatives', config('freeci.payments.provider')],
            ['Paiements sandbox autorisés (FREECI_PAYMENT_SANDBOX)', $yes((bool) config('freeci.payments.sandbox_enabled'))],
            ['URL de base HTTPS', $yes(! in_array('base_url_not_https', $problems, true))],
            ['Clés présentes', $yes(! in_array('keys_missing', $problems, true))],
            ['Clés du BAC À SABLE (pk_sandbox_ / sk_sandbox_)', $yes(! array_intersect(['keys_missing', 'keys_not_sandbox', 'live_keys_refused'], $problems))],
            ['Clés « live » détectées (refusées)', $yes(in_array('live_keys_refused', $problems, true))],
            ['Secret du webhook présent', $yes(! in_array('webhook_secret_missing', $problems, true))],
            ['Mode réel', 'NON AUTORISÉ dans cette version'],
            ['Intégration prête (bac à sable)', $yes(GeniusPayConfig::ready())],
        ]);
        $this->line('URL du webhook à déclarer : '.rtrim((string) config('app.url'), '/').'/webhooks/geniuspay');
        $this->line('Événements : payment.success, payment.failed, payment.cancelled (et, facultatifs : payment.initiated, payment.refunded).');
        $this->line('Tentatives Genius Pay enregistrées : '.DB::table('payments')->where('provider', 'genius_pay')->count().' · événements reçus : '.DB::table('payment_events')->where('provider', 'genius_pay')->count().' · à revérifier : '.DB::table('payment_events')->where('processing', 'needs_review')->count().'.');

        if ($this->option('ping')) {
            if (! GeniusPayConfig::ready()) {
                $this->error('Configuration incomplète ou non conforme : aucun appel émis.');

                return self::FAILURE;
            }
            $id = $provider->merchantId();
            $id === null ? $this->error('Genius Pay n\'a pas répondu comme attendu (clés refusées, réseau ou URL).') : $this->info('Réponse reçue : compte marchand '.substr($id, 0, 4).'… (identifiant masqué).');

            return $id === null ? self::FAILURE : self::SUCCESS;
        }

        return $problems === [] ? self::SUCCESS : self::FAILURE;
    }
}
