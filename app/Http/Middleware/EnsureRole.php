<?php

namespace App\Http\Middleware;

use App\Support\UserState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = (array) $request->session()->get('auth.user', []);
        // NP-6: role diambil dari DB (cache 60 dtk); sesi hanya fallback.
        $state = UserState::fresh((int) ($user['id'] ?? 0));
        $role = $state['role'] ?? ($user['role'] ?? null);
        if ($state !== null && !$state['is_active']) {
            abort(403);
        }

        if (!$role || !in_array($role, $roles, true)) {
            abort(403);
        }

        return $next($request);
    }
}
