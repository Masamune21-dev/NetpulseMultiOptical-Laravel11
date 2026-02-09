<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->session()->get('auth.logged_in')) {
            return redirect()->to('/login');
        }

        return $next($request);
    }
}
