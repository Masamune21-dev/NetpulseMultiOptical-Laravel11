<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // NP-6: CSRF hanya dikecualikan untuk API v1 (Bearer token, tanpa sesi).
        // Rute web /api/* ber-sesi cookie tetap wajib X-CSRF-TOKEN (disuntik
        // otomatis oleh pembungkus fetch/XHR di layouts/app.blade.php).
        $middleware->validateCsrfTokens(except: [
            'api/v1/*',
        ]);

        // NP-1: limiter `api` (120/menit per user/token/IP) untuk grup routes/api.php.
        $middleware->throttleApi();

        $middleware->alias([
            'legacy.auth' => \App\Http\Middleware\EnsureAuthenticated::class,
            'legacy.role' => \App\Http\Middleware\EnsureRole::class,
            'api.auth' => \App\Http\Middleware\AuthenticateApiToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
