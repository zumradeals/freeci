<?php

use App\Http\Middleware\EnsureAdministrator;
use App\Http\Middleware\EnsureAdminReady;
use App\Http\Middleware\EnsureFreelanceSpace;
use App\Http\Middleware\EnsureStaff;
use App\Http\Middleware\NoStore;
use App\Http\Middleware\RequireRecentAuth;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        then: function () {
            Route::middleware('web')->group(base_path('routes/account.php'));
            Route::group([], base_path('routes/webhooks.php'));
        },
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [SecurityHeaders::class]);
        $middleware->alias(['no-store' => NoStore::class, 'administrator' => EnsureAdministrator::class, 'staff' => EnsureStaff::class, 'admin-ready' => EnsureAdminReady::class, 'recent-auth' => RequireRecentAuth::class, 'freelance' => EnsureFreelanceSpace::class]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('account.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
