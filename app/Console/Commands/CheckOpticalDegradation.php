<?php

namespace App\Console\Commands;

use App\Services\InterfaceDiscovery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Daily optical degradation check.
 *
 * Compares each SFP's recent "best" RX power against a baseline from ~a week
 * ago (both taken from the daily rollup's per-day rx_max, ignoring days the
 * link was down). A sustained drop beyond the threshold signals a dirty
 * connector / bending / aging fiber BEFORE the link actually goes down.
 *
 * Alerts fire once per degradation (re-alert only on a further step worse),
 * with a recovery notice when RX returns to baseline. State lives in
 * interface_degradation_state.
 *
 *   php artisan optical:degradation --dry-run
 *   php artisan optical:degradation --threshold=3
 */
class CheckOpticalDegradation extends Command
{
    protected $signature = 'optical:degradation
        {--threshold=3.0 : dB drop vs baseline that counts as degradation}
        {--recent-days=3 : Recent window size (days) for current RX}
        {--base-from=14 : Baseline window start, days ago (inclusive)}
        {--base-to=7 : Baseline window end, days ago (exclusive)}
        {--min-base-days=3 : Require at least this many up-days of baseline data}
        {--min-rx=-38 : RX above this (dBm) counts as "up"; lower = link down, ignored}
        {--dry-run : Report findings without alerting or writing state}';

    public function handle(InterfaceDiscovery $discovery): int
    {
        $threshold = (float) $this->option('threshold');
        $recent = max(1, (int) $this->option('recent-days'));
        $bfrom = max(2, (int) $this->option('base-from'));
        $bto = max(1, (int) $this->option('base-to'));
        $minBaseDays = max(1, (int) $this->option('min-base-days'));
        $minRx = (float) $this->option('min-rx');
        $dry = (bool) $this->option('dry-run');

        if ($bto >= $bfrom) {
            $this->error('--base-to must be smaller than --base-from (it is "days ago").');
            return self::FAILURE;
        }

        // critical = twice the warning drop
        $criticalDrop = $threshold * 2;

        $rows = DB::select("
            SELECT device_id, if_index,
                   AVG(CASE WHEN bucket >= (CURDATE() - INTERVAL {$recent} DAY) AND rx_max > {$minRx} THEN rx_max END) AS recent_rx,
                   COUNT(CASE WHEN bucket >= (CURDATE() - INTERVAL {$recent} DAY) AND rx_max > {$minRx} THEN 1 END) AS recent_days,
                   AVG(CASE WHEN bucket >= (CURDATE() - INTERVAL {$bfrom} DAY) AND bucket < (CURDATE() - INTERVAL {$bto} DAY) AND rx_max > {$minRx} THEN rx_max END) AS base_rx,
                   COUNT(CASE WHEN bucket >= (CURDATE() - INTERVAL {$bfrom} DAY) AND bucket < (CURDATE() - INTERVAL {$bto} DAY) AND rx_max > {$minRx} THEN 1 END) AS base_days
            FROM interface_stats_daily
            WHERE bucket >= (CURDATE() - INTERVAL {$bfrom} DAY)
            GROUP BY device_id, if_index
            HAVING recent_days >= 1 AND base_days >= {$minBaseDays}
        ");

        $degraded = 0;
        $recovered = 0;
        $checked = 0;

        foreach ($rows as $r) {
            if ($r->recent_rx === null || $r->base_rx === null) {
                continue;
            }
            $checked++;

            $recentRx = round((float) $r->recent_rx, 2);
            $baseRx = round((float) $r->base_rx, 2);
            $drop = round($baseRx - $recentRx, 2); // positive = RX got worse

            $state = DB::table('interface_degradation_state')
                ->where('device_id', $r->device_id)
                ->where('if_index', $r->if_index)
                ->first();
            $wasDegraded = $state && $state->status === 'degraded';
            $prevDrop = $state && $state->drop_db !== null ? (float) $state->drop_db : 0.0;

            $isDegraded = $drop >= $threshold;

            if ($isDegraded) {
                // Alert on a new degradation, or when it worsens by a further step.
                $isNew = !$wasDegraded;
                $worsened = $wasDegraded && ($drop >= $prevDrop + $threshold);

                if ($isNew || $worsened) {
                    $degraded++;
                    $severity = $drop >= $criticalDrop ? 'critical' : 'warning';
                    $this->emit($discovery, $r, $recentRx, $baseRx, $drop, $severity, 'interface_degradation', $dry);
                }

                $this->saveState($r, 'degraded', $baseRx, $recentRx, $drop, ($isNew || $worsened), $dry);
            } else {
                // Recovery: was degraded, RX now back near baseline (hysteresis).
                if ($wasDegraded && $drop < ($threshold * 0.5)) {
                    $recovered++;
                    $this->emit($discovery, $r, $recentRx, $baseRx, $drop, 'info', 'interface_recovered', $dry);
                    $this->saveState($r, 'ok', $baseRx, $recentRx, $drop, true, $dry);
                } elseif ($state) {
                    // Keep latest readings; clear status if it had recovered fully.
                    $this->saveState($r, $wasDegraded ? 'degraded' : 'ok', $baseRx, $recentRx, $drop, false, $dry);
                }
            }
        }

        $this->info(($dry ? '[dry-run] ' : '') . "Checked {$checked} interface(s): {$degraded} degradation alert(s), {$recovered} recovery alert(s).");
        return self::SUCCESS;
    }

    private function emit(InterfaceDiscovery $discovery, $r, float $recentRx, float $baseRx, float $drop, string $severity, string $eventType, bool $dry): void
    {
        $iface = DB::table('interfaces')
            ->leftJoin('snmp_devices', 'interfaces.device_id', '=', 'snmp_devices.id')
            ->where('interfaces.device_id', $r->device_id)
            ->where('interfaces.if_index', $r->if_index)
            ->select([
                'interfaces.if_name', 'interfaces.if_alias',
                'snmp_devices.device_name', 'snmp_devices.ip_address',
            ])
            ->first();

        $ip = $iface->ip_address ?? '';
        $deviceName = $iface->device_name ?? '';
        $ifName = $iface->if_name ?? ('ifIndex ' . $r->if_index);
        $ifAlias = trim((string) ($iface->if_alias ?? ''));

        $deviceLabel = trim($deviceName . ' (' . $ip . ')');
        $ifaceLabel = $ifAlias !== '' ? "{$ifName} ({$ifAlias})" : $ifName;
        $time = date('Y-m-d H:i:s');

        if ($eventType === 'interface_recovered') {
            $log = "Optical recovered: {$deviceLabel} / {$ifaceLabel} (RX {$recentRx} dBm, baseline {$baseRx} dBm)";
            $tg = "🟢 OPTICAL RECOVERED\n📟 Device: {$deviceLabel}\n🔌 Interface: {$ifaceLabel}\n📡 RX: {$recentRx} dBm (baseline {$baseRx} dBm)\n🕒 Time: {$time}";
        } else {
            $log = "Optical degradation: {$deviceLabel} / {$ifaceLabel} — RX {$recentRx} dBm, down {$drop} dB from baseline {$baseRx} dBm";
            $tg = "🟠 OPTICAL DEGRADATION\n📟 Device: {$deviceLabel}\n🔌 Interface: {$ifaceLabel}\n📉 RX: {$recentRx} dBm (turun {$drop} dB dari baseline {$baseRx} dBm)\n🕒 Time: {$time}";
        }

        $this->line('  ' . ($dry ? '[would alert] ' : '') . $log);

        if ($dry) {
            return;
        }

        $discovery->emitDegradationAlert(
            [
                'device_id' => (int) $r->device_id,
                'device_name' => (string) $deviceName,
                'device_ip' => (string) $ip,
            ],
            [
                'if_index' => (int) $r->if_index,
                'if_name' => (string) $ifName,
                'if_alias' => (string) $ifAlias,
                'rx_power' => $recentRx,
                'tx_power' => null,
            ],
            $eventType,
            $severity,
            $log,
            $tg
        );
    }

    private function saveState($r, string $status, float $baseRx, float $recentRx, float $drop, bool $touchAlerted, bool $dry): void
    {
        if ($dry) {
            return;
        }

        $data = [
            'status' => $status,
            'baseline_dbm' => $baseRx,
            'recent_dbm' => $recentRx,
            'drop_db' => $drop,
            'updated_at' => now(),
        ];
        if ($touchAlerted) {
            $data['alerted_at'] = now();
        }

        DB::table('interface_degradation_state')->updateOrInsert(
            ['device_id' => (int) $r->device_id, 'if_index' => (int) $r->if_index],
            $data
        );
    }
}
