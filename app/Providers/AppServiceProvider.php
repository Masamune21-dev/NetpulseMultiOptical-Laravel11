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

            $throttled = function (Request $request, array $headers) use ($username) {
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
            };

            // Dua batas: 5/menit per username+IP (tebak satu akun) dan 20/menit per IP saja.
            // Tanpa yang kedua, satu IP bebas mencoba satu kata sandi ke banyak username
            // sekaligus (password spraying) karena tiap username punya kuotanya sendiri.
            return [
                Limit::perMinute(5)->by($username . '|' . $request->ip())->response($throttled),
                Limit::perMinute(20)->by('login-ip|' . $request->ip())->response($throttled),
            ];
        });

        // Uji profil optik & deteksi ulang vendor menembak SNMP ke perangkat produksi:
        // dibatasi 6/menit per admin supaya tombol yang ditekan berulang tidak membebani switch.
        RateLimiter::for('optical-snmp', function (Request $request) {
            $user = (array) $request->session()->get('auth.user', []);

            return Limit::perMinute(6)->by('optical|' . ($user['id'] ?? $request->ip()));
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
