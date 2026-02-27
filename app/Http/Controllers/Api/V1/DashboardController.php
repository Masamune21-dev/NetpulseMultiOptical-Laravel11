<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ViewerDummyData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $role = (string) ($user->role ?? '');

        if ($role === 'viewer') {
            $c = ViewerDummyData::dashboardCounts();
            return response()->json([
                'success' => true,
                'data' => [
                    'device_count' => $c['deviceCount'],
                    'interface_count' => $c['ifCount'],
                    'sfp_count' => $c['sfpCount'],
                    'bad_optical_count' => $c['badOptical'],
                    'user_count' => $c['userCount'],
                    'olt_count' => $c['oltCount'],
                    'pon_count' => $c['ponCount'],
                    'onu_count' => $c['onuCount'],
                ],
            ]);
        }

        if (!in_array($role, ['admin', 'technician'], true)) {
            return response()->json(['success' => false, 'error' => 'Forbidden'], 403);
        }

        $counts = DB::selectOne("
            SELECT
                (SELECT COUNT(*) FROM snmp_devices WHERE is_active = 1) as device_count,
                (SELECT COUNT(*) FROM interfaces) as interface_count,
                (SELECT COUNT(*) FROM interfaces WHERE is_sfp = 1 AND tx_power IS NOT NULL) as sfp_count
        ");

        $badOpticalCount = 0;
        if (Schema::hasTable('alert_logs')) {
            $badRow = DB::selectOne("
                SELECT COUNT(*) as bad_optical_count
                FROM interfaces i
                WHERE i.is_sfp = 1
                  AND i.oper_status IS NOT NULL
                  AND i.oper_status <> 1
                  AND EXISTS (
                      SELECT 1
                      FROM alert_logs al
                      WHERE al.device_id = i.device_id
                        AND al.if_index = i.if_index
                        AND al.event_type = 'interface_down'
                  )
            ");
            $badOpticalCount = (int) ($badRow->bad_optical_count ?? 0);
        }

        $userCountRow = DB::selectOne("SELECT COUNT(*) as user_count FROM users");

        $oltConfig = config('olt', []);
        [$oltCount, $ponCount, $onuCount] = $this->computeOltSummary($oltConfig);

        return response()->json([
            'success' => true,
            'data' => [
                'device_count' => (int) ($counts->device_count ?? 0),
                'interface_count' => (int) ($counts->interface_count ?? 0),
                'sfp_count' => (int) ($counts->sfp_count ?? 0),
                'bad_optical_count' => $badOpticalCount,
                'user_count' => (int) ($userCountRow->user_count ?? 0),
                'olt_count' => $oltCount,
                'pon_count' => $ponCount,
                'onu_count' => $onuCount,
            ],
        ]);
    }

    private function computeOltSummary(array $oltConfig): array
    {
        $root = rtrim(storage_path('app/olt'), DIRECTORY_SEPARATOR);
        $oltCount = count($oltConfig);
        $ponCount = 0;
        $onuCount = 0;

        foreach ($oltConfig as $oltId => $olt) {
            $pons = $olt['pons'] ?? [];
            $ponCount += count($pons);

            foreach ($pons as $pon) {
                $ponSafe = str_replace('/', '_', $pon);
                $jsonFile = $root . "/{$oltId}/pon_{$ponSafe}.json";
                if (!is_file($jsonFile)) {
                    continue;
                }
                $json = json_decode(file_get_contents($jsonFile), true);
                if (is_array($json) && isset($json['total'])) {
                    $onuCount += (int) $json['total'];
                }
            }
        }

        return [$oltCount, $ponCount, $onuCount];
    }
}
