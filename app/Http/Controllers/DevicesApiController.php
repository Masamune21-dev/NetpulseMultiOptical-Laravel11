<?php

namespace App\Http\Controllers;

use App\Support\ViewerDummyData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DevicesApiController extends Controller
{
    public function index(Request $request)
    {
        $testId = (int) $request->query('test', 0);
        if (ViewerDummyData::isViewer($request)) {
            if ($testId > 0) {
                return response()->json(['status' => 'OK', 'response' => 'SNMP OK (dummy)']);
            }
            return response()->json(ViewerDummyData::devices());
        }

        if ($testId > 0) {
            return $this->testSnmp($testId);
        }

        $devices = DB::table('snmp_devices')
            ->orderByDesc('id')
            ->get();

        return response()->json($devices);
    }

    public function store(Request $request)
    {
        $user = $request->session()->get('auth.user');
        if (($user['role'] ?? '') !== 'admin') {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $data = $request->json()->all();
        $payload = [
            'device_name' => $data['device_name'] ?? '',
            'ip_address' => $data['ip_address'] ?? '',
            'snmp_version' => $data['snmp_version'] ?? '2c',
            'community' => $data['community'] ?? null,
            'snmp_user' => $data['snmp_user'] ?? null,
            'is_active' => (int) ($data['is_active'] ?? 1),
        ];

        if (empty($data['id'])) {
            DB::table('snmp_devices')->insert($payload);
        } else {
            DB::table('snmp_devices')
                ->where('id', (int) $data['id'])
                ->update($payload);
        }

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request)
    {
        $user = $request->session()->get('auth.user');
        if (($user['role'] ?? '') !== 'admin') {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $id = (int) ($request->query('id', 0) ?: $request->input('id', 0));
        if ($id <= 0) {
            return response()->json(['success' => false, 'error' => 'Invalid device id'], 400);
        }

        try {
            $deleted = DB::transaction(function () use ($id) {
                // Clean up dependent data when tables exist.
                if (Schema::hasTable('interfaces')) {
                    $ifaceIds = DB::table('interfaces')->where('device_id', $id)->pluck('id')->all();
                    if (!empty($ifaceIds) && Schema::hasTable('map_links')) {
                        DB::table('map_links')
                            ->where(function ($q) use ($ifaceIds) {
                                $q->whereIn('interface_a_id', $ifaceIds)
                                    ->orWhereIn('interface_b_id', $ifaceIds);
                            })
                            ->delete();
                    }
                    DB::table('interfaces')->where('device_id', $id)->delete();
                }

                if (Schema::hasTable('map_nodes')) {
                    DB::table('map_nodes')->where('device_id', $id)->update(['device_id' => null]);
                }

                if (Schema::hasTable('alert_logs')) {
                    DB::table('alert_logs')->where('device_id', $id)->update(['device_id' => null]);
                }

                return DB::table('snmp_devices')->where('id', $id)->delete();
            });
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => 'Delete failed'], 500);
        }

        if ($deleted <= 0) {
            return response()->json(['success' => false, 'error' => 'Device not found'], 404);
        }

        return response()->json(['success' => true]);
    }

    private function testSnmp(int $id)
    {
        $device = DB::table('snmp_devices')->where('id', $id)->first();
        if (!$device) {
            return response()->json(['status' => 'FAILED', 'error' => 'Device not found'], 404);
        }

        $ip = $device->ip_address;
        $oid = '1.3.6.1.2.1.1.1.0';
        $timeout = 1000000;
        $retries = 1;

        try {
            if ($device->snmp_version === '2c') {
                if (!function_exists('snmp2_get')) {
                    throw new \RuntimeException('SNMP extension not installed');
                }
                $response = @\snmp2_get($ip, (string) $device->community, $oid, $timeout, $retries);
            } else {
                if (!function_exists('snmp3_get')) {
                    throw new \RuntimeException('SNMP extension not installed');
                }
                $response = @\snmp3_get($ip, (string) $device->snmp_user, 'noAuthNoPriv', '', '', $oid, $timeout, $retries);
            }

            if ($response === false) {
                throw new \RuntimeException('SNMP timeout / auth failed');
            }

            DB::table('snmp_devices')
                ->where('id', $id)
                ->update(['last_status' => 'OK', 'last_error' => null]);

            return response()->json(['status' => 'OK', 'response' => $response]);
        } catch (\Throwable $e) {
            $err = substr($e->getMessage(), 0, 250);
            DB::table('snmp_devices')
                ->where('id', $id)
                ->update(['last_status' => 'FAILED', 'last_error' => $err]);

            return response()->json(['status' => 'FAILED', 'error' => $err]);
        }
    }
}
