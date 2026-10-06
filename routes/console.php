<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

// Persistance des expirations ; facultatif (cron « * * * * * php artisan schedule:run »), l'exactitude ne dépend pas de ce passage.
Schedule::command('freeci:orders:expire')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('freeci:files:scan')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('freeci:media:prune')->daily();
// Courriels de notification : relance des envois restés en attente ; vidage de la file par le planificateur SEULEMENT si demandé
// (FREECI_QUEUE_VIA_SCHEDULER=true), sinon un processus « queue:work » dédié est recommandé (deploy/freeci-queue.service.example).
Schedule::command('freeci:notifications:retry --stale')->everyTenMinutes()->withoutOverlapping();
// Paiements Genius Pay (bac à sable) : reprise des événements enregistrés, interrogation des tentatives ouvertes, signalement des tentatives expirées.
Schedule::command('freeci:payments:reconcile')->everyFiveMinutes()->withoutOverlapping();
if (config('freeci.notifications.queue_via_scheduler')) {
    Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=5')->everyMinute()->withoutOverlapping();
}
