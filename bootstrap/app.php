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
        $middleware->validateCsrfTokens(except: [
            'logout',
            'logout.php',
            'dashboard',
            'dashboard.php',
            'monitoring',
            'monitoring.php',
            'devices',
            'devices.php',
            'map',
            'map.php',
            'users',
            'users.php',
            'settings',
            'settings.php',
            'olt',
            'olt.php',
            'api/settings.php',
            'api/telegram_test.php',
            'api/logs.php',
            'api/*',
        ]);

        $middleware->alias([
            'legacy.auth' => \App\Http\Middleware\EnsureAuthenticated::class,
            'legacy.role' => \App\Http\Middleware\EnsureRole::class,
            'api.auth' => \App\Http\Middleware\AuthenticateApiToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
