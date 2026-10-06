<?php

use App\Http\Controllers\GeniusPayWebhookController;
use Illuminate\Support\Facades\Route;

// Notifications de prestataire : hors groupe « web » (ni session, ni CSRF), authentifiées par signature, limitées en débit.
Route::middleware('throttle:120,1')->prefix('webhooks')->group(function () {
    Route::post('/geniuspay', GeniusPayWebhookController::class)->name('webhooks.geniuspay');
});
