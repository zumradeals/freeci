<?php

use App\Http\Controllers\Account\DashboardController;
use App\Http\Controllers\Admin\AdminHomeController;
use Illuminate\Support\Facades\Route;

// Espace privé : toute route ici exige une session authentifiée ; réponses jamais mises en cache partagé.
Route::middleware(['auth', 'no-store'])->prefix('espace')->group(function () {
    Route::get('/', DashboardController::class)->name('account.dashboard');
});

// Coquille d'administration, séparée de l'espace client : habilitation « administrateur » en vigueur exigée.
Route::middleware(['auth', 'no-store', 'administrator'])->prefix('admin')->group(function () {
    Route::get('/', AdminHomeController::class)->name('admin.home');
});
