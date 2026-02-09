<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $username = $data['username'];
        $ip = $request->ip();

        $user = User::query()
            ->where('username', $username)
            ->first();

        if (!$user) {
            $this->writeSecurityLog('LOGIN_FAILED', $username, $ip, 'User not found');
            return back()->withErrors(['username' => 'Invalid username or password'])->withInput();
        }

        if ((int) $user->is_active !== 1) {
            $this->writeSecurityLog('LOGIN_FAILED', $user->username, $ip, 'Account disabled');
            return back()->withErrors(['username' => 'Account is disabled. Contact administrator.'])->withInput();
        }

        $passwordOk = Hash::check($data['password'], $user->password);
        if (!$passwordOk && hash_equals($data['password'], (string) $user->password)) {
            $user->password = Hash::make($data['password']);
            $user->save();
            $passwordOk = true;
        }

        if (!$passwordOk) {
            $this->writeSecurityLog('LOGIN_FAILED', $user->username, $ip, 'Invalid password');
            return back()->withErrors(['username' => 'Invalid username or password'])->withInput();
        }

        $request->session()->regenerate();
        $request->session()->put('auth.logged_in', true);
        $request->session()->put('auth.user', [
            'id' => $user->id,
            'username' => $user->username,
            'full_name' => $user->full_name,
            'role' => $user->role,
        ]);

        $this->writeSecurityLog('LOGIN_SUCCESS', $user->username, $ip, 'OK');

        return redirect()->to('/dashboard');
    }

    public function logout(Request $request)
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->to('/login');
    }

    private function writeSecurityLog(string $event, string $username, ?string $ip, string $message): void
    {
        $logDir = storage_path('logs');
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $line = sprintf(
            "[%s] [%s] [%s] user=%s msg=%s",
            date('Y-m-d H:i:s'),
            $ip !== '' ? $ip : '-',
            $event,
            $username !== '' ? $username : '-',
            $message
        );

        @file_put_contents($logDir . DIRECTORY_SEPARATOR . 'security.log', $line . PHP_EOL, FILE_APPEND);
    }
}
