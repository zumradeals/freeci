<?php

namespace App\Console\Commands;

use App\Modules\Finance\Actions\ProcessProviderEvent;
use App\Modules\Finance\Actions\RefreshPaymentStatus;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\ReconciliationCase;
use App\Modules\Finance\SandboxGate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rapprochement des paiements Genius Pay (bac à sable) : (1) reprend les événements enregistrés mais non traités ou à revérifier, (2) interroge le
 * prestataire pour les tentatives OUVERTES (notification manquante, création interrompue : même demande rejouée, jamais une nouvelle tentative),
 * (3) signale les tentatives restées ouvertes au-delà de l'expiration du lien. Aucune confirmation sans revérification ; aucun démarrage automatique de travail.
 */
class PaymentsReconcile extends Command
{
    protected $signature = 'freeci:payments:reconcile {--limit=50 : nombre maximal de lignes par passe}';

    protected $description = 'Rapproche les paiements Genius Pay (bac à sable) : événements à reprendre, tentatives ouvertes à vérifier, tentatives expirées à signaler.';

    public function handle(ProcessProviderEvent $process, RefreshPaymentStatus $refresh): int
    {
        if (! SandboxGate::enabled()) {
            $this->line('Paiements sandbox désactivés : rien à faire.');

            return self::SUCCESS;
        }
        $limit = max(1, (int) $this->option('limit'));
        $events = 0;
        DB::table('payment_events')->where('provider', 'genius_pay')->where(function ($q) {
            $q->where(fn ($w) => $w->where('processing', 'received')->where('received_at', '<', now()->subSeconds(30)))
                ->orWhere(fn ($w) => $w->where('processing', 'needs_review')->where('attempts', '<', 6)->where('processed_at', '<', now()->subMinutes(5)));
        })->orderBy('id')->limit($limit)->pluck('id')->each(function ($id) use ($process, &$events) {
            $process->process((int) $id);
            $events++;
        });

        $polled = 0;
        Payment::query()->where('provider', 'genius_pay')->whereIn('state', ['created', 'pending', 'unknown'])->where('created_at', '<', now()->subMinutes(2))
            ->where(fn ($q) => $q->whereNull('last_checked_at')->orWhere('last_checked_at', '<', now()->subMinutes(5)))->orderBy('created_at')->limit($limit)->get()
            ->each(function (Payment $p) use ($refresh, &$polled) {
                $refresh->forPayment($p);
                $polled++;
            });

        $stale = 0;
        Payment::query()->where('provider', 'genius_pay')->whereIn('state', ['created', 'pending', 'unknown'])->whereNotNull('provider_expires_at')->where('provider_expires_at', '<', now()->subHour())->get()
            ->each(function (Payment $p) use (&$stale) {
                $c = ReconciliationCase::query()->firstOrCreate(['order_id' => $p->order_id, 'payment_id' => $p->getKey(), 'reason' => 'pending_past_expiry'], ['details' => ['environment' => $p->environment, 'expired_at' => $p->provider_expires_at?->toIso8601String()]]);
                $stale += $c->wasRecentlyCreated ? 1 : 0;
            });

        $this->info("Événements repris : {$events} · tentatives interrogées : {$polled} · tentatives signalées (au-delà de l'expiration) : {$stale}.");

        return self::SUCCESS;
    }
}
