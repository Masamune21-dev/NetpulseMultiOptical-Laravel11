<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $counts = DB::selectOne("
            SELECT
                (SELECT COUNT(*) FROM snmp_devices WHERE is_active = 1) as device_count,
                (SELECT COUNT(*) FROM interfaces) as interface_count,
                (SELECT COUNT(*) FROM interfaces WHERE is_sfp = 1 AND tx_power IS NOT NULL) as sfp_count,
                (SELECT COUNT(*) FROM interfaces
                 WHERE is_sfp = 1
                 AND tx_power IS NOT NULL
                 AND (tx_power - rx_power) > 30) as bad_optical_count
        ");

        $userCountRow = DB::selectOne("SELECT COUNT(*) as user_count FROM users");

        $oltConfig = config('olt', []);
        [$oltCount, $ponCount, $onuCount] = $this->computeOltSummary($oltConfig);

        return view('dashboard.index', [
            'pageTitle' => 'Dashboard',
            'deviceCount' => (int) ($counts->device_count ?? 0),
            'ifCount' => (int) ($counts->interface_count ?? 0),
            'sfpCount' => (int) ($counts->sfp_count ?? 0),
            'badOptical' => (int) ($counts->bad_optical_count ?? 0),
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
