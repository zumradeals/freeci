<?php

use App\Http\Controllers\Account\DashboardController;
use Illuminate\Support\Facades\Route;

// Espace privé : toute route ici exige une session authentifiée ; réponses jamais mises en cache partagé.
Route::middleware(['auth', 'no-store'])->prefix('espace')->group(function () {
    Route::get('/', DashboardController::class)->name('account.dashboard');
});
