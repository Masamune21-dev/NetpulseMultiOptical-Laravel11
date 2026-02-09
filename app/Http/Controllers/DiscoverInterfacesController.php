<?php

namespace App\Http\Controllers;

use App\Services\InterfaceDiscovery;
use Illuminate\Http\Request;

class DiscoverInterfacesController extends Controller
{
    public function __invoke(Request $request, InterfaceDiscovery $discovery)
    {
        $deviceId = (int) $request->query('device_id', 0);
        if ($deviceId <= 0) {
            return response()->json(['success' => false, 'error' => 'Invalid device_id'], 400);
        }

        $result = $discovery->discover($deviceId, false);
        if (!($result['success'] ?? false)) {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }
}
