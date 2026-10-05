<?php

namespace App\Console\Commands;

use App\Integrations\Payments\SandboxPaymentProvider;
use App\Modules\Finance\SandboxGate;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pilote le prestataire SIMULÉ côté opérateur (console du serveur) : fixe l'issue d'une transaction simulée et, si demandé,
 * envoie des notifications SIGNÉES par la même route que n'importe quel prestataire (même chemin de code, mêmes contrôles).
 * C'est le SEUL moyen de faire aboutir un paiement simulé : aucun bouton, aucun paramètre d'URL.
 */
class SandboxResolve extends Command
{
    protected $signature = 'freeci:sandbox:resolve {reference : référence du paiement (SBX-…)}
        {outcome : succeeded | failed | pending | indeterminate : issue côté prestataire simulé}
        {--notify : envoie une notification signée correspondant à l\'issue}
        {--events= : liste ordonnée de statuts à notifier (ex. succeeded,succeeded,failed) : doublons et désordre}
        {--prefix= : préfixe des identifiants d\'événements (défaut aléatoire ; le même préfixe rejoue les mêmes événements)}';

    protected $description = 'Fixe l\'issue d\'un paiement simulé et/ou envoie des notifications signées (simulateur uniquement).';

    public function handle(): int
    {
        if (! SandboxGate::enabled()) {
            $this->error('Simulateur désactivé (FREECI_PAYMENT_SANDBOX=false) : aucune action.');

            return self::FAILURE;
        }
        $reference = (string) $this->argument('reference');
        $outcome = (string) $this->argument('outcome');
        if (! in_array($outcome, ['succeeded', 'failed', 'pending', 'indeterminate'], true)) {
            $this->error('Issue invalide.');

            return self::FAILURE;
        }
        if (! DB::table('sandbox_transactions')->where('reference', $reference)->exists()) {
            $this->error('Référence inconnue du simulateur.');

            return self::FAILURE;
        }
        DB::table('sandbox_transactions')->where('reference', $reference)->update(['status' => $outcome, 'updated_at' => now()]);
        $this->info("Prestataire simulé : {$reference} → {$outcome}.");

        $statuses = $this->option('events') ? array_map('trim', explode(',', (string) $this->option('events'))) : ($this->option('notify') ? [$outcome] : []);
        $prefix = (string) ($this->option('prefix') ?: Str::lower(Str::random(8)));
        foreach ($statuses as $i => $status) {
            $this->line(sprintf('Notification %d (%s) : %s', $i + 1, $status, $this->notify($reference, $status, $prefix.'-'.($i + 1))));
        }

        return self::SUCCESS;
    }

    private function notify(string $reference, string $status, string $eventId): string
    {
        $secret = (string) config('freeci.payments.sandbox_webhook_secret');
        if ($secret === '') {
            return 'ignorée : FREECI_SANDBOX_WEBHOOK_SECRET non défini';
        }
        $body = json_encode(['id' => $eventId, 'reference' => $reference, 'status' => $status], JSON_THROW_ON_ERROR);
        $request = Request::create('/webhooks/sandbox-payments', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SANDBOX_SIGNATURE' => SandboxPaymentProvider::signatureHeader($secret, $body),
        ], $body);
        $response = app(Kernel::class)->handle($request);

        return $response->getStatusCode().' '.$response->getContent();
    }
}
