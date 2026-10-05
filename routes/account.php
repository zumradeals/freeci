<?php

use App\Http\Controllers\Account\DashboardController;
use App\Http\Controllers\Admin\AdminHomeController;
use App\Http\Controllers\Freelance\FreelanceController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderRequestController;
use Illuminate\Support\Facades\Route;

// Espace privé : toute route ici exige une session authentifiée ; réponses jamais mises en cache partagé.
Route::middleware(['auth', 'no-store'])->group(function () {
    Route::get('/espace', DashboardController::class)->name('account.dashboard');
    Route::get('/espace/commandes', [OrderController::class, 'index'])->name('orders.index');

    // Demande de prestation (client) — la reprise après connexion revient ici (URL « intended » interne).
    Route::get('/services/{slug}/demande', [OrderRequestController::class, 'create'])->name('services.request');
    Route::post('/services/{slug}/demande', [OrderRequestController::class, 'store'])->middleware('throttle:12,1')->name('services.request.store');

    // Dossier commun : lecture bornée aux deux parties ; chaque transition est une action serveur confirmée.
    Route::get('/commandes/{reference}', [OrderController::class, 'show'])->name('orders.show');
    Route::get('/commandes/{reference}/{action}', [OrderController::class, 'confirm'])->whereIn('action', ['accept', 'decline', 'withdraw', 'cancel'])->name('orders.confirm');
    Route::post('/commandes/{reference}/{action}', [OrderController::class, 'act'])->whereIn('action', ['accept', 'decline', 'withdraw', 'cancel'])->middleware('throttle:30,1')->name('orders.act');

    // Espace freelance : activation puis pages réservées au rôle freelance.
    Route::get('/freelance/activer', [FreelanceController::class, 'activate'])->name('freelance.activate');
    Route::post('/freelance/profil', [FreelanceController::class, 'save'])->middleware('throttle:20,1')->name('freelance.profile.save');
    Route::middleware('freelance')->prefix('freelance')->group(function () {
        Route::get('/', [FreelanceController::class, 'dashboard'])->name('freelance.dashboard');
        Route::get('/commandes', [FreelanceController::class, 'orders'])->name('freelance.orders');
        Route::get('/services', [FreelanceController::class, 'services'])->name('freelance.services');
        Route::get('/profil', [FreelanceController::class, 'profile'])->name('freelance.profile');
    });
});

// Coquille d'administration, séparée de l'espace client : habilitation « administrateur » en vigueur exigée.
Route::middleware(['auth', 'no-store', 'administrator'])->prefix('admin')->group(function () {
    Route::get('/', AdminHomeController::class)->name('admin.home');
});
