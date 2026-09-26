<?php

namespace App\Http\Middleware;

use App\Support\UserState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $session = $request->session();
        if (!$session->get('auth.logged_in')) {
            return redirect()->to('/login');
        }

        // NP-6: role & is_active dimuat ulang dari DB (cache 60 dtk), bukan
        // hanya dari salinan di sesi saat login.
        $user = (array) $session->get('auth.user', []);
        $state = UserState::fresh((int) ($user['id'] ?? 0));

        // Sidik sandi terikat id user pemilik sesi: [uid, fp].
        $userId = (int) ($user['id'] ?? 0);
        $sessionPw = (array) $session->get('auth.pw', []);
        if ($state !== null && (int) ($sessionPw['uid'] ?? 0) !== $userId) {
            // Sesi lama sebelum sidik ini ada — inisialisasi tanpa menendang.
            $sessionPw = ['uid' => $userId, 'fp' => $state['pw'] ?? ''];
            $session->put('auth.pw', $sessionPw);
        }

        // Sandi direset admin (mis. akun bocor) → sesi web lama ikut gugur, bukan
        // bertahan 120 menit bergulir lewat polling dashboard. `pw` bisa absen di
        // entri cache UserState lama (≤60 dtk setelah pembaruan) — lewati.
        $passwordChanged = $state !== null && isset($state['pw'])
            && !hash_equals($state['pw'], (string) ($sessionPw['fp'] ?? ''));

        if ($state === null || !$state['is_active'] || $passwordChanged) {
            $session->invalidate();
            $session->regenerateToken();

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['error' => 'Unauthenticated'], 401);
            }

            return redirect()->to('/login');
        }

        if (($user['role'] ?? null) !== $state['role']) {
            $user['role'] = $state['role'];
            $session->put('auth.user', $user);
        }

        return $next($request);
    }
}
