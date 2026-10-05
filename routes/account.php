<?php

use App\Http\Controllers\Account\DashboardController;
use App\Http\Controllers\Admin\AdminHomeController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\Freelance\FreelanceController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderFileController;
use App\Http\Controllers\OrderRequestController;
use App\Http\Controllers\PaymentController;
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

    // Paiement SIMULÉ (réservé aux commandes et comptes de démonstration autorisés) : état lu en base, jamais déduit de l'URL.
    Route::get('/commandes/{reference}/paiement', [PaymentController::class, 'show'])->name('orders.payment');
    Route::post('/commandes/{reference}/paiement', [PaymentController::class, 'pay'])->middleware('throttle:10,1')->name('orders.payment.start');
    Route::post('/commandes/{reference}/paiement/actualiser', [PaymentController::class, 'refresh'])->middleware('throttle:20,1')->name('orders.payment.refresh');

    // Pièces jointes privées du brief.
    Route::post('/commandes/{reference}/brief/fichiers', [OrderFileController::class, 'store'])->middleware('throttle:20,1')->name('orders.files.store');
    Route::post('/commandes/{reference}/brief/fichiers/{file}/retirer', [OrderFileController::class, 'destroy'])->name('orders.files.destroy');
    Route::get('/commandes/{reference}/fichiers/{file}', [OrderFileController::class, 'download'])->middleware('signed')->name('orders.files.download');

    // Livraison, corrections, report d'échéance, validation. Les routes fixes passent AVANT {action} (contraint) : aucun conflit.
    Route::get('/commandes/{reference}/livraison', [DeliveryController::class, 'edit'])->name('orders.delivery');
    Route::post('/commandes/{reference}/livraison/message', [DeliveryController::class, 'saveMessage'])->middleware('throttle:30,1')->name('orders.delivery.message');
    Route::post('/commandes/{reference}/livraison/fichiers', [DeliveryController::class, 'upload'])->middleware('throttle:20,1')->name('orders.delivery.upload');
    Route::post('/commandes/{reference}/livraison/fichiers/{file}/retirer', [DeliveryController::class, 'removeFile'])->name('orders.delivery.remove');
    Route::get('/commandes/{reference}/livraison/soumettre', [DeliveryController::class, 'submitConfirm'])->name('orders.delivery.confirm');
    Route::post('/commandes/{reference}/livraison/soumettre', [DeliveryController::class, 'submit'])->middleware('throttle:10,1')->name('orders.delivery.submit');
    Route::get('/commandes/{reference}/correction', [DeliveryController::class, 'correctionForm'])->name('orders.correction');
    Route::post('/commandes/{reference}/correction', [DeliveryController::class, 'correction'])->middleware('throttle:10,1')->name('orders.correction.store');
    Route::get('/commandes/{reference}/validation', [DeliveryController::class, 'validateForm'])->name('orders.validation');
    Route::post('/commandes/{reference}/validation', [DeliveryController::class, 'validateDelivery'])->middleware('throttle:10,1')->name('orders.validation.store');
    Route::get('/commandes/{reference}/report', [DeliveryController::class, 'extensionForm'])->name('orders.extension');
    Route::post('/commandes/{reference}/report', [DeliveryController::class, 'extension'])->middleware('throttle:10,1')->name('orders.extension.store');
    Route::post('/commandes/{reference}/report/retirer', [DeliveryController::class, 'extensionWithdraw'])->middleware('throttle:10,1')->name('orders.extension.withdraw');
    Route::get('/commandes/{reference}/report/{decision}', [DeliveryController::class, 'extensionAnswerForm'])->whereIn('decision', ['accepter', 'refuser'])->name('orders.extension.answer');
    Route::post('/commandes/{reference}/report/{decision}', [DeliveryController::class, 'extensionAnswer'])->whereIn('decision', ['accepter', 'refuser'])->middleware('throttle:10,1')->name('orders.extension.answer.store');

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
