<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Ambang RX global (dBm) — satu sumber untuk web dan API mobile.
 *
 * Mobile dulu memegang angka lokal yang berbeda antar layar (−25, −35, −40);
 * kini semua layar membaca nilai ini dari respons API.
 */
final class RxThresholds
{
    public const DEFAULT_WARN_LOW = -25.0;
    public const DEFAULT_DOWN = -40.0;

    /** @return array{rx_warn_low: float, rx_down_threshold: float} */
    public static function global(): array
    {
        try {
            $rows = DB::table('settings')
                ->whereIn('name', ['alert_rx_warning_low', 'alert_rx_down_threshold'])
                ->pluck('value', 'name');
        } catch (\Throwable) {
            $rows = collect();
        }

        return [
            'rx_warn_low' => is_numeric($rows['alert_rx_warning_low'] ?? null)
                ? (float) $rows['alert_rx_warning_low'] : self::DEFAULT_WARN_LOW,
            'rx_down_threshold' => is_numeric($rows['alert_rx_down_threshold'] ?? null)
                ? (float) $rows['alert_rx_down_threshold'] : self::DEFAULT_DOWN,
        ];
    }

    /**
     * Riwayat status per jam (24 bucket, terlama → terbaru) untuk sekumpulan
     * (device_id, if_index). Huruf: u = up, w = marjinal, d = down, n = tanpa data.
     *
     * @param  array<int, array{0:int,1:int}>  $pairs
     * @return array<string, string>  kunci "device_id:if_index" → string 24 huruf
     */
    public static function history24h(array $pairs, array $thresholds): array
    {
        $out = [];
        foreach ($pairs as [$d, $i]) {
            $out["$d:$i"] = str_repeat('n', 24);
        }
        if ($pairs === []) {
            return $out;
        }

        $warn = $thresholds['rx_warn_low'];
        $down = $thresholds['rx_down_threshold'];
        $start = now()->subHours(23)->startOfHour();

        $query = DB::table('interface_stats_hourly')
            ->select(['device_id', 'if_index', 'bucket', 'rx_min', 'rx_avg'])
            ->where('bucket', '>=', $start)
            ->where(function ($q) use ($pairs) {
                foreach ($pairs as [$d, $i]) {
                    $q->orWhere(fn ($w) => $w->where('device_id', $d)->where('if_index', $i));
                }
            });

        foreach ($query->cursor() as $row) {
            $key = "{$row->device_id}:{$row->if_index}";
            if (!isset($out[$key])) {
                continue;
            }
            $slot = (int) floor(($start->diffInMinutes($row->bucket, false)) / 60);
            if ($slot < 0 || $slot > 23) {
                continue;
            }
            $min = $row->rx_min !== null ? (float) $row->rx_min : null;
            $avg = $row->rx_avg !== null ? (float) $row->rx_avg : null;
            if ($min === null && $avg === null) {
                $ch = 'n';
            } elseif ($min !== null && $min <= $down) {
                $ch = 'd';
            } elseif ($avg !== null && $avg < $warn) {
                $ch = 'w';
            } else {
                $ch = 'u';
            }
            $out[$key][$slot] = $ch;
        }

        return $out;
    }
}
