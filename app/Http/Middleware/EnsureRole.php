<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->session()->get('auth.user');
        $role = $user['role'] ?? null;

        if (!$role || !in_array($role, $roles, true)) {
            abort(403);
        }

        return $next($request);
    }
}
