<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ViewerDummyData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MapController extends Controller
{
    public function nodes(Request $request)
    {
        $user = $request->user();
        $role = (string) ($user->role ?? '');

        $withInterfaces = (int) $request->query('with_interfaces', 0) === 1;

        if ($role === 'viewer') {
            return response()->json(['success' => true, 'data' => ViewerDummyData::mapNodes($withInterfaces)]);
        }

        if (!in_array($role, ['admin', 'technician', 'viewer'], true)) {
            return response()->json(['success' => false, 'error' => 'Forbidden'], 403);
        }

        if (!Schema::hasTable('map_nodes')) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $rows = DB::select("
            SELECT mn.*,
                   sd.device_name,
                   sd.ip_address,
                   sd.last_status,
                   sd.snmp_version
            FROM map_nodes mn
            LEFT JOIN snmp_devices sd ON mn.device_id = sd.id
            ORDER BY mn.created_at DESC
        ");

        $nodes = [];
        foreach ($rows as $row) {
            $node = (array) $row;
            $node['status'] = $node['last_status'] ?? 'unknown';
            $node['interfaces'] = [];
            $node['interfaces_loaded'] = false;

            $isOnline = in_array($node['status'], ['OK', 'Up', 'online'], true);
            if ($withInterfaces && !empty($node['device_id']) && $isOnline && Schema::hasTable('interfaces')) {
                $ifs = DB::table('interfaces')
                    ->select([
                        'if_name',
                        'if_alias',
                        'if_description',
                        'if_type',
                        'is_sfp',
                        'last_seen',
                        'rx_power',
                        'tx_power',
                        'interface_type',
                        'oper_status',
                    ])
                    ->where('device_id', $node['device_id'])
                    ->where('is_monitored', 1)
                    ->orderBy('if_index')
                    ->limit(200)
                    ->get();

                foreach ($ifs as $ifRow) {
                    $rowArr = (array) $ifRow;
                    if (array_key_exists('rx_power', $rowArr)) {
                        $rowArr['rx_power'] = $rowArr['rx_power'] !== null ? (float) $rowArr['rx_power'] : null;
                    }
                    if (array_key_exists('tx_power', $rowArr)) {
                        $rowArr['tx_power'] = $rowArr['tx_power'] !== null ? (float) $rowArr['tx_power'] : null;
                    }
                    $node['interfaces'][] = $rowArr;
                }
                $node['interfaces_loaded'] = true;
            }

            $nodes[] = $node;
        }

        return response()->json(['success' => true, 'data' => $nodes]);
    }

    public function links(Request $request)
    {
        $user = $request->user();
        $role = (string) ($user->role ?? '');

        if ($role === 'viewer') {
            return response()->json(['success' => true, 'data' => ViewerDummyData::mapLinks()]);
        }

        if (!in_array($role, ['admin', 'technician', 'viewer'], true)) {
            return response()->json(['success' => false, 'error' => 'Forbidden'], 403);
        }

        if (!Schema::hasTable('map_links')) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $hasInterfacesTable = Schema::hasTable('interfaces');
        if ($hasInterfacesTable) {
            $rows = DB::select("
                SELECT ml.*,
                       na.node_name AS node_a_name,
                       nb.node_name AS node_b_name,
                       COALESCE(ia.if_name, ia.if_alias) AS interface_a_name,
                       COALESCE(ib.if_name, ib.if_alias) AS interface_b_name,
                       ia.oper_status AS interface_a_status,
                       ib.oper_status AS interface_b_status,
                       ia.rx_power AS interface_a_rx,
                       ia.tx_power AS interface_a_tx,
                       ib.rx_power AS interface_b_rx,
                       ib.tx_power AS interface_b_tx
                FROM map_links ml
                LEFT JOIN map_nodes na ON ml.node_a_id = na.id
                LEFT JOIN map_nodes nb ON ml.node_b_id = nb.id
                LEFT JOIN interfaces ia ON ml.interface_a_id = ia.id
                LEFT JOIN interfaces ib ON ml.interface_b_id = ib.id
                ORDER BY ml.created_at DESC
            ");
        } else {
            $rows = DB::select("
                SELECT ml.*,
                       na.node_name AS node_a_name,
                       nb.node_name AS node_b_name,
                       NULL AS interface_a_name,
                       NULL AS interface_b_name,
                       NULL AS interface_a_status,
                       NULL AS interface_b_status,
                       NULL AS interface_a_rx,
                       NULL AS interface_a_tx,
                       NULL AS interface_b_rx,
                       NULL AS interface_b_tx
                FROM map_links ml
                LEFT JOIN map_nodes na ON ml.node_a_id = na.id
                LEFT JOIN map_nodes nb ON ml.node_b_id = nb.id
                ORDER BY ml.created_at DESC
            ");
        }

        $links = [];
        foreach ($rows as $row) {
            $row = (array) $row;
            $row['interface_a_status'] = isset($row['interface_a_status']) ? (int) $row['interface_a_status'] : null;
            $row['interface_b_status'] = isset($row['interface_b_status']) ? (int) $row['interface_b_status'] : null;
            $row['interface_a_rx'] = $row['interface_a_rx'] !== null ? (float) $row['interface_a_rx'] : null;
            $row['interface_a_tx'] = $row['interface_a_tx'] !== null ? (float) $row['interface_a_tx'] : null;
            $row['interface_b_rx'] = $row['interface_b_rx'] !== null ? (float) $row['interface_b_rx'] : null;
            $row['interface_b_tx'] = $row['interface_b_tx'] !== null ? (float) $row['interface_b_tx'] : null;
            $links[] = $row;
        }

        return response()->json(['success' => true, 'data' => $links]);
    }
}
