<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ViewerDummyData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MonitoringController extends Controller
{
    public function devices(Request $request)
    {
        $user = $request->user();
        $role = (string) ($user->role ?? '');

        if ($role === 'viewer') {
            return response()->json(['success' => true, 'data' => ViewerDummyData::monitoringDevices()]);
        }

        if (!in_array($role, ['admin', 'technician'], true)) {
            return response()->json(['success' => false, 'error' => 'Forbidden'], 403);
        }

        $devices = DB::table('snmp_devices')
            ->select(['id', 'device_name'])
            ->where('is_active', 1)
            ->orderBy('device_name')
            ->get();

        return response()->json(['success' => true, 'data' => $devices]);
    }

    public function interfaces(Request $request)
    {
        $user = $request->user();
        $role = (string) ($user->role ?? '');

        $deviceId = (int) $request->query('device_id', 0);
        if ($deviceId <= 0) {
            return response()->json(['success' => true, 'data' => []]);
        }

        if ($role === 'viewer') {
            return response()->json(['success' => true, 'data' => ViewerDummyData::monitoringInterfaces($deviceId)]);
        }

        if (!in_array($role, ['admin', 'technician'], true)) {
            return response()->json(['success' => false, 'error' => 'Forbidden'], 403);
        }

        $rows = DB::table('interfaces')
            ->select(['if_index', 'if_name', 'if_alias', 'tx_power', 'rx_power'])
            ->where('device_id', $deviceId)
            ->where('is_sfp', 1)
            ->orderBy('if_index')
            ->get();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function chart(Request $request)
    {
        $user = $request->user();
        $role = (string) ($user->role ?? '');

        $deviceId = (int) $request->query('device_id', 0);
        $ifIndex = (int) $request->query('if_index', 0);
        $range = (string) $request->query('range', '1h');

        if ($deviceId <= 0 || $ifIndex <= 0) {
            return response()->json(['success' => true, 'data' => []]);
        }

        if ($role === 'viewer') {
            return response()->json([
                'success' => true,
                'data' => ViewerDummyData::interfaceChart($deviceId, $ifIndex, $range),
            ]);
        }

        if (!in_array($role, ['admin', 'technician'], true)) {
            return response()->json(['success' => false, 'error' => 'Forbidden'], 403);
        }

        $interval = match ($range) {
            '1h' => '1 HOUR',
            '1d' => '24 HOUR',
            '3d' => '72 HOUR',
            '7d' => '7 DAY',
            '30d' => '30 DAY',
            '1y' => '1 YEAR',
            default => '1 HOUR',
        };

        $sql = str_contains($interval, 'YEAR') || str_contains($interval, 'DAY')
            ? "SELECT created_at, tx_power, rx_power, loss
               FROM interface_stats
               WHERE device_id = ? AND if_index = ?
                 AND created_at >= DATE_SUB(NOW(), INTERVAL $interval)
               ORDER BY created_at ASC"
            : "SELECT created_at, tx_power, rx_power, loss
               FROM interface_stats
               WHERE device_id = ? AND if_index = ?
                 AND created_at >= NOW() - INTERVAL $interval
               ORDER BY created_at ASC";

        $rows = DB::select($sql, [$deviceId, $ifIndex]);

        $defaultDownRx = -40.00;
        $data = [];

        foreach ($rows as $row) {
            $rx = $row->rx_power;
            $tx = $row->tx_power;

            if ($rx === null || $rx === '') {
                $rx = $defaultDownRx;
            } else {
                $rx = (float) $rx;
            }

            if ($tx !== null && $tx !== '') {
                $tx = (float) $tx;
            } else {
                $tx = null;
            }

            $loss = ($tx !== null && $rx !== null) ? $tx - $rx : null;

            $data[] = [
                'created_at' => (string) $row->created_at,
                'tx_power' => $tx,
                'rx_power' => $rx,
                'loss' => $loss,
            ];
        }

        return response()->json(['success' => true, 'data' => $data]);
    }
}

