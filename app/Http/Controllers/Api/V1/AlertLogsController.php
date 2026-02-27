<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ViewerDummyData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AlertLogsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $role = (string) ($user->role ?? '');

        if ($role === 'viewer') {
            return response()->json(['success' => true, 'data' => ViewerDummyData::alertLogs()]);
        }

        if (!in_array($role, ['admin', 'technician'], true)) {
            return response()->json(['success' => false, 'error' => 'Forbidden'], 403);
        }

        if (!Schema::hasTable('alert_logs')) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $limit = (int) $request->query('limit', 200);
        if ($limit <= 0) $limit = 200;
        if ($limit > 500) $limit = 500;

        $type = trim((string) $request->query('type', 'all'));
        $severity = trim((string) $request->query('severity', 'all'));
        $q = trim((string) $request->query('q', ''));

        $query = DB::table('alert_logs')->orderByDesc('id')->limit($limit);

        if ($type !== '' && $type !== 'all') {
            $query->where('event_type', $type);
        }

        if ($severity !== '' && $severity !== 'all') {
            $query->where('severity', $severity);
        }

        if ($q !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
            $query->where(function ($sub) use ($like) {
                $sub->where('message', 'like', $like)
                    ->orWhere('device_name', 'like', $like)
                    ->orWhere('device_ip', 'like', $like)
                    ->orWhere('if_name', 'like', $like)
                    ->orWhere('if_alias', 'like', $like);
            });
        }

        $rows = $query->get();

        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                'id' => (int) $r->id,
                'created_at' => (string) $r->created_at,
                'event_type' => (string) $r->event_type,
                'severity' => (string) $r->severity,
                'device_id' => $r->device_id !== null ? (int) $r->device_id : null,
                'device_name' => $r->device_name !== null ? (string) $r->device_name : null,
                'device_ip' => $r->device_ip !== null ? (string) $r->device_ip : null,
                'if_index' => $r->if_index !== null ? (int) $r->if_index : null,
                'if_name' => $r->if_name !== null ? (string) $r->if_name : null,
                'if_alias' => $r->if_alias !== null ? (string) $r->if_alias : null,
                'rx_power' => $r->rx_power !== null ? (float) $r->rx_power : null,
                'tx_power' => $r->tx_power !== null ? (float) $r->tx_power : null,
                'message' => (string) $r->message,
            ];
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function destroy(Request $request)
    {
        $user = $request->user();
        $role = (string) ($user->role ?? '');

        if ($role !== 'admin') {
            return response()->json(['success' => false, 'error' => 'Forbidden'], 403);
        }

        if (!Schema::hasTable('alert_logs')) {
            return response()->json(['success' => true]);
        }

        DB::table('alert_logs')->delete();
        return response()->json(['success' => true]);
    }
}

