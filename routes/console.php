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
