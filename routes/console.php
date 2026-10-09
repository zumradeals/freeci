<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use App\Shared\TaskHeartbeat;
use Illuminate\Support\Facades\Schedule;

// Persistance des expirations ; facultatif (cron « * * * * * php artisan schedule:run »), l'exactitude ne dépend pas de ce passage.
TaskHeartbeat::watch(Schedule::command('freeci:orders:expire')->everyFiveMinutes()->withoutOverlapping(), 'orders:expire');
TaskHeartbeat::watch(Schedule::command('freeci:files:scan')->everyFiveMinutes()->withoutOverlapping(), 'files:scan');
TaskHeartbeat::watch(Schedule::command('freeci:media:prune')->daily(), 'media:prune');
TaskHeartbeat::watch(Schedule::command('freeci:photos:purge')->daily(), 'photos:purge');
// Retours de disponibilité : constate les dates de retour échues (la disponibilité elle-même est évaluée à la lecture) et prévient le freelance.
TaskHeartbeat::watch(Schedule::command('freeci:availability:reopen')->dailyAt('00:10'), 'availability:reopen');
// Courriels de notification : relance des envois restés en attente ; vidage de la file par le planificateur SEULEMENT si demandé
// (FREECI_QUEUE_VIA_SCHEDULER=true), sinon un processus « queue:work » dédié est recommandé (deploy/freeci-queue.service.example).
TaskHeartbeat::watch(Schedule::command('freeci:notifications:retry --stale')->everyTenMinutes()->withoutOverlapping(), 'notifications:retry');
// Paiements Genius Pay (bac à sable) : reprise des événements enregistrés, interrogation des tentatives ouvertes, signalement des tentatives expirées.
TaskHeartbeat::watch(Schedule::command('freeci:payments:reconcile')->everyFiveMinutes()->withoutOverlapping(), 'payments:reconcile');
if (config('freeci.notifications.queue_via_scheduler')) {
    Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=5')->everyMinute()->withoutOverlapping();
}
// Remboursements Genius Pay envoyés par API : lecture du paiement chez le prestataire, jamais de renvoi (lot 11).
TaskHeartbeat::watch(Schedule::command('freeci:finance:reconcile')->everyFiveMinutes()->withoutOverlapping(), 'finance:reconcile');
// Avis devenus publics : notification du freelance (idempotent ; l'affichage public n'en dépend pas).
TaskHeartbeat::watch(Schedule::command('freeci:reviews:publish')->hourly()->withoutOverlapping(), 'reviews:publish');
// Fermeture de comptes : traite les demandes échues (anonymise seulement s'il n'y a aucune obligation en cours).
TaskHeartbeat::watch(Schedule::command('freeci:accounts:close')->hourly()->withoutOverlapping(), 'accounts:close');
