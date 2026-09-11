<?php

namespace App\Providers;

use App\Support\SecurityLog;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('*', function ($view) {
            $view->with('currentUser', session('auth.user'));
        });

        $this->configureRateLimiting();
    }

    /**
     * NP-1: throttle login (web + API v1) dan limiter umum grup `api`.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $username = strtolower(trim((string) $request->input('username', '')));

            return Limit::perMinute(5)
                ->by($username . '|' . $request->ip())
                ->response(function (Request $request, array $headers) use ($username) {
                    SecurityLog::write('LOGIN_THROTTLED', $username, $request->ip(), 'Too many attempts');

                    if ($request->is('api/*') || $request->expectsJson()) {
                        return response()->json(
                            ['error' => 'Terlalu banyak percobaan login. Coba lagi dalam 1 menit.'],
                            429,
                            $headers
                        );
                    }

                    return redirect()
                        ->to('/login')
                        ->withErrors(['username' => 'Terlalu banyak percobaan login. Coba lagi dalam 1 menit.'])
                        ->withInput($request->only('username'));
                });
        });

        RateLimiter::for('api', function (Request $request) {
            $user = $request->user();
            $key = $user?->getAuthIdentifier()
                ?? ($request->bearerToken() ? 'tok:' . hash('sha256', $request->bearerToken()) : null)
                ?? $request->ip();

            return Limit::perMinute(120)->by((string) $key);
        });
    }
}
