<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * SLA / uptime report from interface_down_events: which interfaces went down,
 * how often over a period, total downtime and availability, plus a per-event
 * drilldown (each down's start/end/duration).
 */
class SlaController extends Controller
{
    public function index()
    {
        return view('sla.index');
    }

    /** GET /api/sla — per-interface summary over the last N days. */
    public function summary(Request $request)
    {
        $days = $this->days($request);
        $deviceId = (int) $request->query('device_id', 0);
        $q = trim((string) $request->query('q', ''));

        $bindings = [];
        $where = ["COALESCE(up_at, NOW()) >= NOW() - INTERVAL {$days} DAY"];
        if ($deviceId > 0) {
            $where[] = 'device_id = ?';
            $bindings[] = $deviceId;
        }
        if ($q !== '') {
            $where[] = '(device_name LIKE ? OR if_name LIKE ? OR if_alias LIKE ?)';
            $like = '%' . $q . '%';
            array_push($bindings, $like, $like, $like);
        }
        $whereSql = implode(' AND ', $where);

        $rows = DB::select("
            SELECT device_id, if_index,
                   MAX(device_name) AS device_name,
                   MAX(if_name) AS if_name,
                   MAX(if_alias) AS if_alias,
                   SUM(CASE WHEN down_at >= NOW() - INTERVAL {$days} DAY THEN 1 ELSE 0 END) AS down_count,
                   SUM(GREATEST(0, TIMESTAMPDIFF(SECOND,
                        GREATEST(down_at, NOW() - INTERVAL {$days} DAY),
                        COALESCE(up_at, NOW())))) AS down_sec,
                   MAX(CASE WHEN up_at IS NULL THEN 1 ELSE 0 END) AS still_down,
                   MAX(down_at) AS last_down_at
            FROM interface_down_events
            WHERE {$whereSql}
            GROUP BY device_id, if_index
            HAVING down_count > 0
            ORDER BY down_count DESC, down_sec DESC
        ", $bindings);

        $windowSec = $days * 86400;
        $data = array_map(function ($r) use ($windowSec) {
            $downSec = (int) $r->down_sec;
            $avail = $windowSec > 0 ? max(0, min(100, (1 - $downSec / $windowSec) * 100)) : null;
            return [
                'device_id' => (int) $r->device_id,
                'if_index' => (int) $r->if_index,
                'device_name' => $r->device_name,
                'if_name' => $r->if_name,
                'if_alias' => $r->if_alias,
                'down_count' => (int) $r->down_count,
                'down_sec' => $downSec,
                'availability' => $avail !== null ? round($avail, 3) : null,
                'still_down' => (int) $r->still_down === 1,
                'last_down_at' => $r->last_down_at,
            ];
        }, $rows);

        return response()->json([
            'success' => true,
            'days' => $days,
            'window_sec' => $windowSec,
            'interfaces' => $data,
            'totals' => [
                'interfaces' => count($data),
                'down_events' => array_sum(array_column($data, 'down_count')),
                'down_sec' => array_sum(array_column($data, 'down_sec')),
            ],
        ]);
    }

    /** GET /api/sla/export — CSV of the per-interface SLA summary. */
    public function export(Request $request)
    {
        $days = $this->days($request);
        $json = $this->summary($request)->getData(true);
        $rows = $json['interfaces'] ?? [];

        $filename = 'sla_report_' . $days . 'd_' . date('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Device', 'Interface', 'Alias', 'Down count', 'Total downtime', 'Downtime (sec)', 'Availability %', 'Status', 'Last down']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r['device_name'],
                    $r['if_name'],
                    $r['if_alias'],
                    $r['down_count'],
                    $this->humanDuration((int) $r['down_sec']),
                    $r['down_sec'],
                    $r['availability'] !== null ? number_format($r['availability'], 3) : '',
                    $r['still_down'] ? 'DOWN' : 'up',
                    $r['last_down_at'],
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function humanDuration(int $sec): string
    {
        if ($sec < 60) {
            return $sec . 's';
        }
        $d = intdiv($sec, 86400);
        $h = intdiv($sec % 86400, 3600);
        $m = intdiv($sec % 3600, 60);
        $parts = [];
        if ($d) {
            $parts[] = $d . 'd';
        }
        if ($h) {
            $parts[] = $h . 'h';
        }
        if ($m) {
            $parts[] = $m . 'm';
        }
        return $parts ? implode(' ', $parts) : '0m';
    }

    /** GET /api/sla/events — each down event for one interface over the period. */
    public function events(Request $request)
    {
        $days = $this->days($request);
        $deviceId = (int) $request->query('device_id', 0);
        $ifIndex = (int) $request->query('if_index', 0);
        if ($deviceId <= 0 || $ifIndex <= 0) {
            return response()->json(['success' => false, 'error' => 'Missing device_id or if_index'], 400);
        }

        $rows = DB::select("
            SELECT down_at, up_at,
                   COALESCE(duration_sec, TIMESTAMPDIFF(SECOND, down_at, NOW())) AS duration_sec
            FROM interface_down_events
            WHERE device_id = ? AND if_index = ?
              AND COALESCE(up_at, NOW()) >= NOW() - INTERVAL {$days} DAY
            ORDER BY down_at DESC
        ", [$deviceId, $ifIndex]);

        $events = array_map(fn ($r) => [
            'down_at' => $r->down_at,
            'up_at' => $r->up_at,
            'duration_sec' => (int) $r->duration_sec,
            'ongoing' => $r->up_at === null,
        ], $rows);

        return response()->json(['success' => true, 'days' => $days, 'events' => $events]);
    }

    private function days(Request $request): int
    {
        $d = (int) $request->query('days', 30);
        return max(1, min(365, $d));
    }
}
