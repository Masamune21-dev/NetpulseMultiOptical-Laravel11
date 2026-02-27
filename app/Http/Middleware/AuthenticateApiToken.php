<?php

namespace App\Http\Middleware;

use App\Models\PersonalAccessToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->bearerToken($request);
        if ($token === null) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $accessToken = PersonalAccessToken::findToken($token);
        if (!$accessToken) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if ($accessToken->expires_at !== null && $accessToken->expires_at->isPast()) {
            return response()->json(['error' => 'Token expired'], 401);
        }

        $tokenable = $accessToken->tokenable;
        if (!$tokenable) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        Auth::setUser($tokenable);
        $request->setUserResolver(fn () => $tokenable);

        $accessToken->forceFill(['last_used_at' => now()])->save();

        return $next($request);
    }

    private function bearerToken(Request $request): ?string
    {
        $header = (string) $request->header('Authorization', '');
        if ($header === '') {
            return null;
        }

        if (stripos($header, 'Bearer ') !== 0) {
            return null;
        }

        $token = trim(substr($header, 7));
        return $token !== '' ? $token : null;
    }
}

