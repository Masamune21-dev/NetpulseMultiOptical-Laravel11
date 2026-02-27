<?php

namespace App\Http\Controllers;

use App\Support\ViewerDummyData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        if (ViewerDummyData::isViewer($request)) {
            $c = ViewerDummyData::dashboardCounts();
            return view('dashboard.index', [
                'pageTitle' => 'Dashboard',
                'deviceCount' => $c['deviceCount'],
                'ifCount' => $c['ifCount'],
                'sfpCount' => $c['sfpCount'],
                'badOptical' => $c['badOptical'],
                'userCount' => $c['userCount'],
                'oltCount' => $c['oltCount'],
                'ponCount' => $c['ponCount'],
                'onuCount' => $c['onuCount'],
            ]);
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

        return view('dashboard.index', [
            'pageTitle' => 'Dashboard',
            'deviceCount' => (int) ($counts->device_count ?? 0),
            'ifCount' => (int) ($counts->interface_count ?? 0),
            'sfpCount' => (int) ($counts->sfp_count ?? 0),
            'badOptical' => $badOpticalCount,
            'userCount' => (int) ($userCountRow->user_count ?? 0),
            'oltCount' => $oltCount,
            'ponCount' => $ponCount,
            'onuCount' => $onuCount,
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
                // Files are written by `olt:collect` into storage/app/olt/<oltId>/pon_<pon>.json
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
