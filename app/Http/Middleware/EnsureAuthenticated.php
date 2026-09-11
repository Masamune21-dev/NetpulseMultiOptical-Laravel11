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

        if ($state === null || !$state['is_active']) {
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
