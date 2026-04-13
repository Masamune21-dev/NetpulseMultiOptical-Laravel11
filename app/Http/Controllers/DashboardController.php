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

        return view('dashboard.index', [
            'pageTitle' => 'Dashboard',
            'deviceCount' => (int) ($counts->device_count ?? 0),
            'ifCount' => (int) ($counts->interface_count ?? 0),
            'sfpCount' => (int) ($counts->sfp_count ?? 0),
            'badOptical' => $badOpticalCount,
            'userCount' => (int) ($userCountRow->user_count ?? 0),
        ]);
    }
}
