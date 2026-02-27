<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PersonalAccessToken;
use App\Models\User;
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
            return response()->json(['error' => 'Invalid username or password'], 401);
        }

        if ((int) ($user->is_active ?? 1) !== 1) {
            return response()->json(['error' => 'Account is disabled'], 403);
        }

        $passwordOk = Hash::check($data['password'], (string) $user->password);
        if (!$passwordOk && hash_equals($data['password'], (string) $user->password)) {
            // Backward-compat: accept legacy plaintext and re-hash.
            $user->password = Hash::make($data['password']);
            $user->save();
            $passwordOk = true;
        }

        if (!$passwordOk) {
            return response()->json(['error' => 'Invalid username or password'], 401);
        }

        $tokenName = $data['device_name'] ?? 'android';
        $newToken = $user->createToken($tokenName);

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $newToken->plainTextToken,
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

