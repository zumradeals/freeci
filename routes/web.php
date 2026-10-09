<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\ComingSoonController;
use App\Http\Controllers\FreelanceDirectoryController;
use App\Http\Controllers\FreelanceProfileController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InfoPageController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\PortfolioMediaController;
use App\Http\Controllers\PublicMissionController;
use App\Http\Controllers\ServiceController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
Route::get('/services/{slug}', [ServiceController::class, 'show'])->name('services.show');

Route::get('/freelances', [FreelanceDirectoryController::class, 'index'])->name('freelances.index');
Route::get('/freelances/{slug}', [FreelanceProfileController::class, 'show'])->name('freelances.show');
Route::get('/missions', [PublicMissionController::class, 'index'])->name('missions.index');
Route::get('/missions/{slug}', [PublicMissionController::class, 'show'])->name('missions.show');
Route::get('/medias/{id}/{variant}', [MediaController::class, 'show'])->name('media.show');
Route::get('/realisations/{id}/{variant}', [PortfolioMediaController::class, 'show'])->whereIn('variant', ['large', 'card'])->name('portfolio.show');
Route::get('/photos/{id}/{variant}', [PhotoController::class, 'show'])->whereIn('variant', ['large', 'small'])->name('photo.show');

// Pages d'information (brouillons tant que le porteur ne les a pas déclarées adoptées).
Route::get('/informations/{page}', InfoPageController::class)->whereIn('page', array_keys(InfoPageController::PAGES))->name('info');

Route::get('/bientot/{feature}', ComingSoonController::class)->name('coming-soon');

Route::middleware('guest')->group(function () {
    Route::get('/inscription', [RegisterController::class, 'create'])->name('register');
    Route::post('/inscription', [RegisterController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/connexion', [LoginController::class, 'create'])->name('login');
    Route::post('/connexion', [LoginController::class, 'store'])->middleware('throttle:20,1');
    Route::get('/connexion/verification', [TwoFactorChallengeController::class, 'show'])->name('login.2fa');
    Route::post('/connexion/verification', [TwoFactorChallengeController::class, 'verify'])->middleware('throttle:10,5')->name('login.2fa.verify');
    Route::post('/connexion/verification/annuler', [TwoFactorChallengeController::class, 'cancel'])->name('login.2fa.cancel');
    Route::get('/mot-de-passe-oublie', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/mot-de-passe-oublie', [PasswordResetController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reinitialisation/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reinitialisation', [PasswordResetController::class, 'update'])->middleware('throttle:10,1')->name('password.update');
});

Route::post('/deconnexion', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');
