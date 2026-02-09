<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class OltController extends Controller
{
    public function index(Request $request)
    {
        $olts = config('olt', []);
        if (!is_array($olts) || empty($olts)) {
            return view('olt.index', [
                'pageTitle' => 'OLT Monitor',
                'olts' => [],
                'oltId' => null,
                'pon' => null,
                'data' => ['pon' => null, 'total' => 0, 'onu' => []],
                'lastUpdate' => '-',
            ]);
        }
        $oltId = $request->query('olt', array_key_first($olts));
        if (!isset($olts[$oltId])) {
            $oltId = array_key_first($olts);
        }

        $pons = $olts[$oltId]['pons'] ?? [];
        $pon = $request->query('pon', $pons[0] ?? null);
        if (!in_array($pon, $pons, true)) {
            $pon = $pons[0] ?? null;
        }

        $data = [
            'pon' => $pon,
            'total' => 0,
            'onu' => [],
        ];

        $jsonFile = null;
        if ($pon) {
            $ponSafe = str_replace('/', '_', $pon);
            $jsonFile = storage_path("app/olt/{$oltId}/pon_{$ponSafe}.json");
            if (is_file($jsonFile)) {
                $decoded = json_decode(file_get_contents($jsonFile), true);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            }
        }

        $lastUpdate = $jsonFile && is_file($jsonFile) ? date('Y-m-d H:i:s', filemtime($jsonFile)) : '-';

        return view('olt.index', [
            'pageTitle' => 'OLT Monitor',
            'olts' => $olts,
            'oltId' => $oltId,
            'pon' => $pon,
            'data' => $data,
            'lastUpdate' => $lastUpdate,
        ]);
    }
}
