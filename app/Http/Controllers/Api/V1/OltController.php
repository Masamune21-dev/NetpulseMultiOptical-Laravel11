<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ViewerDummyData;
use Illuminate\Http\Request;

class OltController extends Controller
{
    public function list(Request $request)
    {
        $user = $request->user();
        $role = (string) ($user->role ?? '');

        if ($role === 'viewer') {
            $out = [];
            foreach (ViewerDummyData::oltConfig() as $id => $olt) {
                $out[] = [
                    'id' => (string) $id,
                    'name' => (string) ($olt['name'] ?? $id),
                    'pons' => array_values((array) ($olt['pons'] ?? [])),
                    'last_poll' => date('Y-m-d H:i:s'),
                    'pon_count' => count((array) ($olt['pons'] ?? [])),
                ];
            }
            return response()->json(['success' => true, 'data' => $out]);
        }

        if (!in_array($role, ['admin', 'technician'], true)) {
            return response()->json(['success' => false, 'error' => 'Forbidden'], 403);
        }

        $olts = config('olt', []);
        $out = [];

        foreach ($olts as $id => $olt) {
            $metaFile = storage_path("app/olt/{$id}/meta.json");
            $meta = null;
            if (is_file($metaFile)) {
                $decoded = json_decode((string) file_get_contents($metaFile), true);
                if (is_array($decoded)) {
                    $meta = $decoded;
                }
            }

            $out[] = [
                'id' => (string) $id,
                'name' => (string) ($olt['name'] ?? $id),
                'pons' => array_values((array) ($olt['pons'] ?? [])),
                'last_poll' => is_array($meta) ? ($meta['last_poll'] ?? null) : null,
                'pon_count' => is_array($meta) ? ($meta['pon_count'] ?? null) : null,
            ];
        }

        return response()->json(['success' => true, 'data' => $out]);
    }

    public function data(Request $request)
    {
        $user = $request->user();
        $role = (string) ($user->role ?? '');

        if ($role === 'viewer') {
            $oltId = (string) $request->query('olt', '');
            $pon = (string) $request->query('pon', '');
            $olts = ViewerDummyData::oltConfig();

            if ($oltId === '' || !isset($olts[$oltId])) {
                return response()->json(['success' => false, 'error' => 'Invalid olt'], 400);
            }
            $pons = (array) ($olts[$oltId]['pons'] ?? []);
            if ($pon === '' || !in_array($pon, $pons, true)) {
                return response()->json(['success' => false, 'error' => 'Invalid pon'], 400);
            }

            return response()->json([
                'success' => true,
                'last_update' => date('Y-m-d H:i:s'),
                'data' => ViewerDummyData::oltPonData($pon),
            ]);
        }

        if (!in_array($role, ['admin', 'technician'], true)) {
            return response()->json(['success' => false, 'error' => 'Forbidden'], 403);
        }

        $olts = config('olt', []);
        $oltId = (string) $request->query('olt', '');
        $pon = (string) $request->query('pon', '');

        if ($oltId === '' || !isset($olts[$oltId])) {
            return response()->json(['success' => false, 'error' => 'Invalid olt'], 400);
        }

        $pons = (array) ($olts[$oltId]['pons'] ?? []);
        if ($pon === '' || !in_array($pon, $pons, true)) {
            return response()->json(['success' => false, 'error' => 'Invalid pon'], 400);
        }

        $ponSafe = str_replace('/', '_', $pon);
        $jsonFile = storage_path("app/olt/{$oltId}/pon_{$ponSafe}.json");
        if (!is_file($jsonFile)) {
            return response()->json(['success' => false, 'error' => 'No data yet'], 404);
        }

        $decoded = json_decode((string) file_get_contents($jsonFile), true);
        if (!is_array($decoded)) {
            return response()->json(['success' => false, 'error' => 'Invalid data file'], 500);
        }

        return response()->json([
            'success' => true,
            'last_update' => date('Y-m-d H:i:s', filemtime($jsonFile)),
            'data' => $decoded,
        ]);
    }
}

