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
        $data = $this->collectData($request);
        return view('dashboard.index', $data);
    }

    public function summary(Request $request)
    {
        $data = $this->collectData($request);
        return response()->json([
            'success' => true,
            'data' => [
                'device_count'      => (int) $data['deviceCount'],
                'device_total'      => (int) ($data['deviceHealth']['total'] ?? 0),
                'interface_count'   => (int) $data['ifCount'],
                'if_up_count'       => (int) $data['ifUpCount'],
                'if_down_count'     => (int) $data['ifDownCount'],
                'sfp_count'         => (int) $data['sfpCount'],
                'bad_optical_count' => (int) $data['badOptical'],
                'user_count'        => (int) $data['userCount'],
                'device_health'     => $data['deviceHealth'],
                'worst_ports'       => collect($data['worstPorts'])->map(fn($p) => (array)$p)->values()->all(),
                'recent_alerts'     => collect($data['recentAlerts'])->map(fn($a) => (array)$a)->values()->all(),
            ],
        ]);
    }

    private function collectData(Request $request): array
    {
        if (ViewerDummyData::isViewer($request)) {
            $c = ViewerDummyData::dashboardCounts();
            return [
                'pageTitle'     => 'Dashboard',
                'deviceCount'   => $c['deviceCount'],
                'ifCount'       => $c['ifCount'],
                'sfpCount'      => $c['sfpCount'],
                'badOptical'    => $c['badOptical'],
                'userCount'     => $c['userCount'],
                'deviceHealth'  => ViewerDummyData::dashboardDeviceHealth(),
                'alertTrend'    => ViewerDummyData::dashboardAlertTrend(),
                'worstPorts'    => ViewerDummyData::dashboardWorstPorts(),
                'recentAlerts'  => ViewerDummyData::dashboardRecentAlerts(),
                'ifUpCount'     => 28,
                'ifDownCount'   => 4,
            ];
        }

        // ── Base KPI counts ─────────────────────────────────────────────────
        $counts = DB::selectOne("
            SELECT
                (SELECT COUNT(*) FROM snmp_devices WHERE is_active = 1)                        AS device_count,
                (SELECT COUNT(*) FROM interfaces)                                               AS interface_count,
                (SELECT COUNT(*) FROM interfaces WHERE is_sfp = 1 AND tx_power IS NOT NULL)    AS sfp_count
        ");

        $badOpticalCount = 0;
        if (Schema::hasTable('alert_logs')) {
            $badRow = DB::selectOne("
                SELECT COUNT(*) AS bad_optical_count
                FROM interfaces i
                WHERE i.is_sfp = 1
                  AND i.oper_status IS NOT NULL
                  AND i.oper_status <> 1
                  AND EXISTS (
                      SELECT 1 FROM alert_logs al
                      WHERE al.device_id = i.device_id
                        AND al.if_name   = i.if_name
                        AND al.event_type = 'interface_down'
                  )
            ");
            $badOpticalCount = (int) ($badRow->bad_optical_count ?? 0);
        }

        $userCountRow = DB::selectOne("SELECT COUNT(*) AS user_count FROM users");

        // ── Device Health Breakdown ──────────────────────────────────────────
        $deviceHealthRow = DB::selectOne("
            SELECT
                COUNT(*)                                                  AS total,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END)           AS active,
                SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END)           AS inactive,
                SUM(CASE WHEN last_status = 'FAILED' THEN 1 ELSE 0 END)  AS failed
            FROM snmp_devices
        ");

        $deviceHealth = [
            'total'    => (int) ($deviceHealthRow->total    ?? 0),
            'active'   => (int) ($deviceHealthRow->active   ?? 0),
            'inactive' => (int) ($deviceHealthRow->inactive ?? 0),
            'failed'   => (int) ($deviceHealthRow->failed   ?? 0),
        ];

        // ── Interface Up / Down counts (SFP only) ───────────────────────────
        $ifStatusRow = DB::selectOne("
            SELECT
                SUM(CASE WHEN oper_status = 1 THEN 1 ELSE 0 END) AS up_count,
                SUM(CASE WHEN oper_status <> 1 THEN 1 ELSE 0 END) AS down_count
            FROM interfaces
            WHERE is_sfp = 1 AND oper_status IS NOT NULL
        ");

        $ifUpCount   = (int) ($ifStatusRow->up_count   ?? 0);
        $ifDownCount = (int) ($ifStatusRow->down_count ?? 0);

        // ── Alert Trend — last 7 days grouped by day + severity ─────────────
        $alertTrend = [];
        if (Schema::hasTable('alert_logs')) {
            $trendRows = DB::select("
                SELECT DATE(created_at) AS day, severity, COUNT(*) AS cnt
                FROM alert_logs
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY DATE(created_at), severity
                ORDER BY day ASC
            ");

            // Build a map: day => [critical=>N, warning=>N, info=>N]
            $dayMap = [];
            foreach ($trendRows as $row) {
                $d = $row->day;
                if (!isset($dayMap[$d])) {
                    $dayMap[$d] = ['critical' => 0, 'warning' => 0, 'info' => 0];
                }
                $sev = strtolower($row->severity);
                if (isset($dayMap[$d][$sev])) {
                    $dayMap[$d][$sev] += (int) $row->cnt;
                }
            }

            // Fill last 7 days (even if empty)
            for ($i = 6; $i >= 0; $i--) {
                $day = date('Y-m-d', strtotime("-{$i} days"));
                $alertTrend[] = [
                    'day'      => $day,
                    'label'    => date('d M', strtotime($day)),
                    'critical' => $dayMap[$day]['critical'] ?? 0,
                    'warning'  => $dayMap[$day]['warning']  ?? 0,
                    'info'     => $dayMap[$day]['info']     ?? 0,
                ];
            }
        } else {
            // alert_logs table doesn't exist yet — return empty 7-day scaffold
            for ($i = 6; $i >= 0; $i--) {
                $day = date('Y-m-d', strtotime("-{$i} days"));
                $alertTrend[] = ['day' => $day, 'label' => date('d M', strtotime($day)), 'critical' => 0, 'warning' => 0, 'info' => 0];
            }
        }

        // ── Worst SFP Ports (6 lowest RX power) ─────────────────────────────
        $worstPorts = DB::select("
            SELECT i.if_name, i.if_alias, i.rx_power, i.tx_power,
                   d.device_name, d.ip_address
            FROM interfaces i
            JOIN snmp_devices d ON d.id = i.device_id
            WHERE i.is_sfp = 1
              AND i.rx_power IS NOT NULL
              AND i.tx_power IS NOT NULL
            ORDER BY i.rx_power ASC
            LIMIT 6
        ");

        // ── Recent Alerts (8 latest) ─────────────────────────────────────────
        $recentAlerts = [];
        if (Schema::hasTable('alert_logs')) {
            $recentAlerts = DB::select("
                SELECT event_type, severity, device_name, if_name, message, created_at
                FROM alert_logs
                ORDER BY created_at DESC
                LIMIT 8
            ");
        }

        return [
            'pageTitle'    => 'Dashboard',
            'deviceCount'  => (int) ($counts->device_count    ?? 0),
            'ifCount'      => (int) ($counts->interface_count ?? 0),
            'sfpCount'     => (int) ($counts->sfp_count       ?? 0),
            'badOptical'   => $badOpticalCount,
            'userCount'    => (int) ($userCountRow->user_count ?? 0),
            'deviceHealth' => $deviceHealth,
            'ifUpCount'    => $ifUpCount,
            'ifDownCount'  => $ifDownCount,
            'alertTrend'   => $alertTrend,
            'worstPorts'   => $worstPorts,
            'recentAlerts' => $recentAlerts,
        ];
    }
}
