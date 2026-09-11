<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Support\SecurityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::query()
            ->where('username', $data['username'])
            ->first();

        if (!$user) {
            SecurityLog::write('API_LOGIN_FAILED', $data['username'], $request->ip(), 'User not found');
            return response()->json(['error' => 'Invalid username or password'], 401);
        }

        if ((int) ($user->is_active ?? 1) !== 1) {
            SecurityLog::write('API_LOGIN_FAILED', $user->username, $request->ip(), 'Account disabled');
            return response()->json(['error' => 'Account is disabled'], 403);
        }

        // NP-5: fallback password plaintext dihapus (lihat users:hash-plaintext-passwords).
        if (!Hash::check($data['password'], (string) $user->password)) {
            SecurityLog::write('API_LOGIN_FAILED', $user->username, $request->ip(), 'Invalid password');
            return response()->json(['error' => 'Invalid username or password'], 401);
        }

        $tokenName = $data['device_name'] ?? 'android';
        // NP-6: token kedaluwarsa 90 hari (ditolak oleh AuthenticateApiToken).
        $newToken = $user->createToken($tokenName, now()->addDays(90));
        SecurityLog::write('API_LOGIN_SUCCESS', $user->username, $request->ip(), 'token=' . $tokenName);

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $newToken->plainTextToken,
            'expires_at' => $newToken->accessToken->expires_at?->toIso8601String(),
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'full_name' => $user->full_name,
                'role' => $user->role,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $token = $this->bearerToken($request);
        if ($token === null) {
            return response()->json(['success' => true]);
        }

        $accessToken = PersonalAccessToken::findToken($token);
        if ($accessToken) {
            $accessToken->delete();
        }

        return response()->json(['success' => true]);
    }

    private function bearerToken(Request $request): ?string
    {
        $header = (string) $request->header('Authorization', '');
        if ($header === '' || stripos($header, 'Bearer ') !== 0) {
            return null;
        }

        $token = trim(substr($header, 7));
        return $token !== '' ? $token : null;
    }
}

