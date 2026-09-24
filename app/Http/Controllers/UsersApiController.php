<?php

namespace App\Http\Controllers;

use App\Models\DeviceToken;
use App\Models\User;
use App\Support\UserState;
use App\Support\ViewerDummyData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UsersApiController extends Controller
{
    private const ROLES = ['admin', 'technician', 'viewer'];

    private const MIN_PASSWORD = 8;

    public function index(Request $request)
    {
        $user = $request->session()->get('auth.user');
        if (ViewerDummyData::isViewer($request)) {
            $currentUserId = (int) ($user['id'] ?? 0);
            return response()->json(ViewerDummyData::users($currentUserId));
        }

        if (!in_array($user['role'] ?? '', ['admin', 'technician'], true)) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        $currentUserId = (int) ($user['id'] ?? 0);
        $users = User::query()
            ->select(['id', 'username', 'full_name', 'role', 'is_active', 'created_at'])
            ->orderBy('id')
            ->get()
            ->map(fn($u) => array_merge($u->toArray(), [
                'is_current' => $u->id === $currentUserId,
            ]));

        return response()->json($users);
    }

    public function store(Request $request)
    {
        $user = $request->session()->get('auth.user');
        if (($user['role'] ?? '') !== 'admin') {
            return response()->json(['success' => false, 'error' => 'Access denied'], 403);
        }

        $data = $request->json()->all();
        $isNew = empty($data['id']);

        if (empty($data['username']) || empty($data['full_name']) || empty($data['role'])) {
            return response()->json(['success' => false, 'error' => 'Username, full name, and role are required'], 400);
        }

        if ($isNew && empty($data['password'])) {
            return response()->json(['success' => false, 'error' => 'Password is required for new user'], 400);
        }

        // Peran hanya dari daftar putih — nilai lain tersimpan apa adanya dan membuat
        // pemeriksaan hak akses berperilaku tak terduga.
        if (!in_array($data['role'], self::ROLES, true)) {
            return response()->json(['success' => false, 'error' => 'Invalid role'], 400);
        }

        if (!empty($data['password']) && mb_strlen((string) $data['password']) < self::MIN_PASSWORD) {
            return response()->json(['success' => false, 'error' => 'Password must be at least ' . self::MIN_PASSWORD . ' characters'], 400);
        }

        if ($isNew) {
            $exists = User::query()->where('username', $data['username'])->exists();
            if ($exists) {
                return response()->json(['success' => false, 'error' => 'Username already exists'], 400);
            }

            User::query()->create([
                'username' => $data['username'],
                'full_name' => $data['full_name'],
                'password' => Hash::make($data['password']),
                'role' => $data['role'],
                'is_active' => (int) ($data['is_active'] ?? 1),
            ]);

            return response()->json(['success' => true, 'message' => 'User added successfully']);
        }

        $target = User::query()->find((int) $data['id']);
        if (!$target) {
            return response()->json(['success' => false, 'error' => 'User not found'], 404);
        }

        $update = [
            'full_name' => $data['full_name'],
            'role' => $data['role'],
            'is_active' => (int) ($data['is_active'] ?? 1),
        ];

        if (!empty($data['password'])) {
            $update['password'] = Hash::make($data['password']);
        }

        // Kata sandi, peran, atau status aktif berubah = kepercayaan lama tidak berlaku lagi:
        // token API (90 hari) milik user itu dicabut supaya token yang bocor tidak tetap
        // bisa dipakai setelah admin mereset kata sandinya. Sesi web sudah ikut lewat
        // UserState (peran & is_active dibaca ulang dari DB).
        $revoke = isset($update['password'])
            || $target->role !== $update['role']
            || (int) $target->is_active !== $update['is_active'];

        $target->fill($update);
        $target->save();
        UserState::forget((int) $target->id);

        if ($revoke) {
            $this->revokeApiAccess($target);
        }

        return response()->json(['success' => true, 'message' => 'User updated successfully']);
    }

    public function destroy(Request $request)
    {
        $user = $request->session()->get('auth.user');
        if (($user['role'] ?? '') !== 'admin') {
            return response()->json(['success' => false, 'error' => 'Access denied'], 403);
        }

        $id = (int) $request->query('id', 0);
        $currentUserId = (int) ($user['id'] ?? 0);

        if ($id <= 0) {
            return response()->json(['success' => false, 'error' => 'Invalid user ID'], 400);
        }

        if ($id === $currentUserId) {
            return response()->json(['success' => false, 'error' => 'Cannot delete your own account'], 400);
        }

        $adminCount = User::query()
            ->where('role', 'admin')
            ->where('id', '!=', $id)
            ->count();

        if ($adminCount < 1) {
            return response()->json(['success' => false, 'error' => 'Cannot delete the last admin user'], 400);
        }

        $victim = User::query()->find($id);
        if ($victim) {
            $this->revokeApiAccess($victim);
        }
        User::query()->where('id', $id)->delete();
        UserState::forget($id);

        return response()->json(['success' => true, 'message' => 'User deleted successfully']);
    }

    /** Cabut semua token API & token push milik user (dipanggil saat kredensial/hak berubah). */
    private function revokeApiAccess(User $target): void
    {
        $target->tokens()->delete();
        DeviceToken::query()->where('user_id', $target->id)->delete();
    }
}
