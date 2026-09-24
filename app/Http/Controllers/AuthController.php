<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\SecurityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /** bcrypt dari string acak; hanya untuk menyamakan waktu respons username tak dikenal. */
    private const DUMMY_HASH = '$2y$12$d9oOoOGKfOJBn8v5Qv5yOOr9udPJL8ygfIpOfs240utphUrPLPrq.';

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

        // Urutan pemeriksaan sengaja: kata sandi dulu, baru status aktif. Dulu akun nonaktif
        // dijawab "Account is disabled" SEBELUM kata sandi dicek, sehingga siapa pun bisa
        // memastikan username mana yang ada. Username tak dikenal tetap menjalankan satu
        // Hash::check tiruan supaya waktu respons tidak membocorkannya.
        $passwordOk = Hash::check($data['password'], $user ? (string) $user->password : self::DUMMY_HASH);

        if (!$user || !$passwordOk) {
            $this->writeSecurityLog('LOGIN_FAILED', $user->username ?? $username, $ip, $user ? 'Invalid password' : 'User not found');
            return back()->withErrors(['username' => 'Invalid username or password'])->withInput();
        }

        if ((int) $user->is_active !== 1) {
            $this->writeSecurityLog('LOGIN_FAILED', $user->username, $ip, 'Account disabled');
            return back()->withErrors(['username' => 'Account is disabled. Contact administrator.'])->withInput();
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
