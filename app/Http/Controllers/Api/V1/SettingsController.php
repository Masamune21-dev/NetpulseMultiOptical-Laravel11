<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ViewerDummyData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $role = (string) ($user->role ?? '');

        if ($role === 'viewer') {
            return response()->json(['success' => true, 'data' => ViewerDummyData::settings()]);
        }

        if (!in_array($role, ['admin', 'technician'], true)) {
            return response()->json(['success' => false, 'error' => 'Forbidden'], 403);
        }

        if (!Schema::hasTable('settings')) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $rows = DB::table('settings')->select(['name', 'value'])->get();
        $data = [];
        foreach ($rows as $row) {
            $data[$row->name] = $row->value;
        }
        return response()->json(['success' => true, 'data' => $data]);
    }

    public function upsert(Request $request)
    {
        $user = $request->user();
        $role = (string) ($user->role ?? '');
        if ($role !== 'admin') {
            return response()->json(['success' => false, 'error' => 'Forbidden'], 403);
        }

        if (!Schema::hasTable('settings')) {
            return response()->json(['success' => false, 'error' => 'Settings table not found'], 500);
        }

        $data = $request->json()->all();
        foreach ($data as $k => $v) {
            DB::statement(
                "INSERT INTO settings (name, value)
                 VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE value = VALUES(value)",
                [(string) $k, (string) $v]
            );
        }

        return response()->json(['success' => true]);
    }

    public function alertPreferences(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'error' => 'Unauthenticated'], 401);
        }

        if (!Schema::hasTable('settings')) {
            return response()->json(['success' => false, 'error' => 'Settings table not found'], 500);
        }

        $key = 'mobile_alert_pref_user_' . $user->id;

        if ($request->isMethod('get')) {
            $val = DB::table('settings')->where('name', $key)->value('value');
            $decoded = $val ? json_decode((string) $val, true) : null;

            if (!is_array($decoded)) {
                $decoded = [
                    'push_enabled' => true,
                    'severity_min' => 'warning',
                ];
            }

            return response()->json(['success' => true, 'data' => $decoded]);
        }

        $data = $request->validate([
            'push_enabled' => ['required', 'boolean'],
            'severity_min' => ['required', 'string', 'in:info,warning,critical'],
        ]);

        $value = json_encode($data, JSON_UNESCAPED_SLASHES);
        DB::statement(
            "INSERT INTO settings (name, value)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value)",
            [$key, $value]
        );

        return response()->json(['success' => true]);
    }
}

