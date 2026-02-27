<?php

namespace App\Http\Controllers;

use App\Services\InterfaceDiscovery;
use App\Support\ViewerDummyData;
use Illuminate\Http\Request;

class DiscoverInterfacesController extends Controller
{
    public function __invoke(Request $request, InterfaceDiscovery $discovery)
    {
        $deviceId = (int) $request->query('device_id', 0);
        if ($deviceId <= 0) {
            return response()->json(['success' => false, 'error' => 'Invalid device_id'], 400);
        }

        if (ViewerDummyData::isViewer($request)) {
            return response()->json([
                'success' => true,
                'inserted' => 0,
                'sfp_count' => 2,
                'sfp_down_count' => 0,
                'optical_found' => 2,
                'message' => 'Viewer mode: dummy discovery (no changes applied)',
            ]);
        }

        $result = $discovery->discover($deviceId, false);
        if (!($result['success'] ?? false)) {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }
}
