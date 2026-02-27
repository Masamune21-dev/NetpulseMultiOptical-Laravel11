<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\ViewerDummyData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UsersApiController extends Controller
{
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
            ->where('id', '!=', $currentUserId)
            ->orderByDesc('id')
            ->get();

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

        $target->fill($update);
        $target->save();

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

        User::query()->where('id', $id)->delete();

        return response()->json(['success' => true, 'message' => 'User deleted successfully']);
    }
}
