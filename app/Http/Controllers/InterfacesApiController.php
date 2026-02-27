<?php

namespace App\Http\Controllers;

use App\Support\ViewerDummyData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InterfacesApiController extends Controller
{
    public function index(Request $request)
    {
        $deviceId = (int) $request->query('device_id', 0);
        if ($deviceId <= 0) {
            return response()->json([]);
        }

        if (ViewerDummyData::isViewer($request)) {
            return response()->json(ViewerDummyData::interfaces($deviceId));
        }

        $deviceExists = DB::table('snmp_devices')->where('id', $deviceId)->exists();
        if (!$deviceExists) {
            return response()->json(['error' => 'Device not found']);
        }

        $columns = Schema::getColumnListing('interfaces');

        $select = ['id', 'if_index', 'if_name', 'optical_index', 'rx_power', 'last_seen', 'is_sfp'];
        if (in_array('tx_power', $columns, true)) {
            $select[] = 'tx_power';
        }
        if (in_array('if_alias', $columns, true)) {
            $select[] = 'if_alias';
        }
        if (in_array('if_description', $columns, true)) {
            $select[] = 'if_description';
        }
        if (in_array('interface_type', $columns, true)) {
            $select[] = 'interface_type';
        }

        $rows = DB::table('interfaces')
            ->select($select)
            ->where('device_id', $deviceId)
            ->orderByDesc('is_sfp')
            ->orderBy('if_index')
            ->get();

        $data = $rows->map(function ($row) {
            $row = (array) $row;
            if (isset($row['is_sfp'])) {
                $row['is_sfp'] = (int) $row['is_sfp'];
            }
            if (array_key_exists('rx_power', $row)) {
                $row['rx_power'] = $row['rx_power'] !== null ? (float) $row['rx_power'] : null;
            }
            if (array_key_exists('tx_power', $row)) {
                $row['tx_power'] = $row['tx_power'] !== null ? (float) $row['tx_power'] : null;
            }
            return $row;
        });

        return response()->json($data->values());
    }
}
