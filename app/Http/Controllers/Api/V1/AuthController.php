<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Support\SecurityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /** bcrypt dari string acak; hanya untuk menyamakan waktu respons username tak dikenal. */
    private const DUMMY_HASH = '$2y$12$d9oOoOGKfOJBn8v5Qv5yOOr9udPJL8ygfIpOfs240utphUrPLPrq.';

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

        // Kata sandi dulu, status aktif sesudahnya — lihat AuthController web. Akun nonaktif
        // hanya diungkap kepada yang sudah memegang kata sandinya yang benar.
        $passwordOk = Hash::check($data['password'], $user ? (string) $user->password : self::DUMMY_HASH);

        if (!$user || !$passwordOk) {
            SecurityLog::write('API_LOGIN_FAILED', $user->username ?? $data['username'], $request->ip(), $user ? 'Invalid password' : 'User not found');
            return response()->json(['error' => 'Invalid username or password'], 401);
        }

        if ((int) ($user->is_active ?? 1) !== 1) {
            SecurityLog::write('API_LOGIN_FAILED', $user->username, $request->ip(), 'Account disabled');
            return response()->json(['error' => 'Account is disabled'], 403);
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
            // Lepas token FCM perangkat ini supaya HP yang sudah logout tidak terus menerima
            // alert akun itu (dan bisa dipakai login akun lain tanpa bentrok kepemilikan).
            $fcmToken = (string) $request->input('fcm_token', '');
            if ($fcmToken !== '') {
                DeviceToken::query()
                    ->where('token', $fcmToken)
                    ->where('user_id', $accessToken->tokenable_id)
                    ->delete();
            }
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

