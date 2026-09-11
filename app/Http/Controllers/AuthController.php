<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\SecurityLog;
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

        // NP-5: fallback password plaintext dihapus; semua users.password sudah
        // di-hash (command users:hash-plaintext-passwords).
        if (!Hash::check($data['password'], (string) $user->password)) {
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
        SecurityLog::write($event, $username, $ip, $message);
    }
}
