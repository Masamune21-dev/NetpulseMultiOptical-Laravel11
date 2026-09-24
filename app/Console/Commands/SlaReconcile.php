<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Close SLA down events that stayed open although the interface recovered.
 *
 * interface_down_events used to be closed only when the poller observed a
 * down->up transition in its alert state. If that state was lost while a link
 * was down, the event stayed open forever and the SLA report kept counting the
 * port as down (COALESCE(up_at, NOW())). The poller now reconciles on its own,
 * but it can only close with "now"; this command repairs history by estimating
 * the real recovery time, most precise source first:
 *
 *   1. first `interface_up` alert log after down_at
 *   2. otherwise the earliest of: first raw interface_stats sample with RX above
 *      the down threshold, and the first hourly rollup bucket (from the hour
 *      after the outage started) whose rx_max is above the threshold
 *
 * Events whose device no longer exists are closed at their last known sample
 * (or at down_at when there is none). Events of interfaces that are still down
 * are left open. Dry run unless --apply is given.
 */
class SlaReconcile extends Command
{
    protected $signature = 'sla:reconcile
        {--apply : Write the changes (default is a dry run)}
        {--ids= : Path to a JSON snapshot of event rows [{id,...}] to (re)evaluate, including ones the poller already closed at "now"}';

    protected $description = 'Close stale SLA down events using the real recovery time';

    public function handle(): int
    {
        if (!Schema::hasTable('interface_down_events')) {
            $this->error('interface_down_events table missing.');
            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $threshold = $this->downThreshold();

        $query = DB::table('interface_down_events');
        $ids = $this->snapshotIds();
        $ids === null ? $query->whereNull('up_at') : $query->whereIn('id', $ids);
        $events = $query->orderBy('down_at')->get();

        $tally = ['reopened' => 0, 'alert_log' => 0, 'raw_stats' => 0, 'hourly' => 0, 'device_gone' => 0, 'still_down' => 0, 'up_unknown' => 0];
        $rows = [];

        foreach ($events as $e) {
            $deviceExists = DB::table('snmp_devices')->where('id', $e->device_id)->exists();
            [$upAt, $source] = $deviceExists
                ? $this->estimateRecovery($e, $threshold)
                : [$this->lastSampleAt($e) ?? (string) $e->down_at, 'device_gone'];

            if ($upAt === null) {
                $iface = DB::table('interfaces')->where('device_id', $e->device_id)->where('if_index', $e->if_index)->first();
                $isUp = $iface && (int) $iface->oper_status === 1 && is_numeric($iface->rx_power) && (float) $iface->rx_power > $threshold;
                if (!$isUp) {
                    $tally['still_down']++;
                    continue; // genuinely down: leave (or keep) it open
                }
                // Up now but no history to date the recovery: keep the poller's value if it
                // already closed it, otherwise close at now.
                $upAt = $e->up_at ?? now()->toDateTimeString();
                $source = 'up_unknown';
            }

            $tally[$source]++;
            $duration = max(0, strtotime($upAt) - strtotime((string) $e->down_at));
            $rows[] = [$e->id, $e->device_id, $e->if_name, $e->down_at, $e->up_at ?? '—', $upAt, $this->human($duration), $source];

            // The link recovered at $upAt — but if it is down NOW, it went down again later
            // and that outage was never recorded (the lost state still said "down").
            $reopenAt = $deviceExists ? $this->laterOutageStart($e, $threshold, $upAt) : null;
            if ($reopenAt) {
                $tally['reopened']++;
                $rows[] = ['baru', $e->device_id, $e->if_name, $reopenAt, '—', '(masih down)', '—', 'dibuka_ulang'];
            }

            if ($apply) {
                DB::transaction(function () use ($e, $upAt, $duration, $reopenAt) {
                    DB::table('interface_down_events')->where('id', $e->id)->update([
                        'up_at' => $upAt,
                        'duration_sec' => $duration,
                    ]);
                    $alreadyOpen = DB::table('interface_down_events')
                        ->where('device_id', $e->device_id)->where('if_index', $e->if_index)
                        ->whereNull('up_at')->exists();
                    if ($reopenAt && !$alreadyOpen) {
                        DB::table('interface_down_events')->insert([
                            'device_id' => $e->device_id,
                            'if_index' => $e->if_index,
                            'if_name' => $e->if_name,
                            'if_alias' => $e->if_alias,
                            'device_name' => $e->device_name,
                            'down_at' => $reopenAt,
                            'up_at' => null,
                            'duration_sec' => null,
                            'created_at' => now(),
                        ]);
                    }
                });
            }
        }

        $this->table(['id', 'device', 'interface', 'down_at', 'up_at lama', 'up_at baru', 'durasi', 'sumber'], $rows);
        $this->info(sprintf(
            '%s: %d dievaluasi — alert_log %d, raw_stats %d, hourly %d, perangkat_hilang %d, masih_down %d (dibiarkan terbuka), up_tanpa_riwayat %d, gangguan_baru_dibuka %d.',
            $apply ? 'DITERAPKAN' : 'DRY RUN',
            count($events), $tally['alert_log'], $tally['raw_stats'], $tally['hourly'],
            $tally['device_gone'], $tally['still_down'], $tally['up_unknown'], $tally['reopened']
        ));

        return self::SUCCESS;
    }

    /** @return array{0:?string,1:string} */
    private function estimateRecovery(object $e, float $threshold): array
    {
        if (Schema::hasTable('alert_logs')) {
            $log = DB::table('alert_logs')
                ->where('device_id', $e->device_id)
                ->where('if_index', $e->if_index)
                ->where('event_type', 'interface_up')
                ->where('created_at', '>', $e->down_at)
                ->orderBy('created_at')
                ->value('created_at');
            if ($log) {
                return [(string) $log, 'alert_log'];
            }
        }

        $raw = DB::table('interface_stats')
            ->where('device_id', $e->device_id)
            ->where('if_index', $e->if_index)
            ->where('created_at', '>', $e->down_at)
            ->where('rx_power', '>', $threshold)
            ->orderBy('created_at')
            ->value('created_at');

        // Hourly rollups start at the hour AFTER the one the link went down in: that
        // hour still holds the good samples from before the outage, and matching it
        // would "close" events of ports that are in fact still down.
        $hourly = null;
        if (Schema::hasTable('interface_stats_hourly')) {
            $hourly = DB::table('interface_stats_hourly')
                ->where('device_id', $e->device_id)
                ->where('if_index', $e->if_index)
                ->where('bucket', '>=', date('Y-m-d H:00:00', strtotime((string) $e->down_at) + 3600))
                ->where('rx_max', '>', $threshold)
                ->orderBy('bucket')
                ->value('bucket');
        }

        // Raw samples only go back as far as their retention, so the earliest of the two
        // is the better estimate (e.g. an outage that ended before raw data began).
        if ($raw && (!$hourly || (string) $raw <= (string) $hourly)) {
            return [(string) $raw, 'raw_stats'];
        }
        if ($hourly) {
            return [(string) $hourly, 'hourly'];
        }

        return [null, 'up_unknown'];
    }

    /**
     * Start of an outage that is still going on and began after $upAt, or null.
     * = first raw sample at/below the threshold after the last good sample.
     */
    private function laterOutageStart(object $e, float $threshold, string $upAt): ?string
    {
        $iface = DB::table('interfaces')->where('device_id', $e->device_id)->where('if_index', $e->if_index)->first();
        $downNow = $iface && ((int) $iface->oper_status !== 1 || !is_numeric($iface->rx_power) || (float) $iface->rx_power <= $threshold);
        if (!$downNow) {
            return null;
        }

        $lastGood = DB::table('interface_stats')
            ->where('device_id', $e->device_id)->where('if_index', $e->if_index)
            ->where('rx_power', '>', $threshold)
            ->max('created_at');
        if (!$lastGood || (string) $lastGood < $upAt) {
            return null;
        }

        $firstBad = DB::table('interface_stats')
            ->where('device_id', $e->device_id)->where('if_index', $e->if_index)
            ->where('created_at', '>', $lastGood)
            ->min('created_at');

        return $firstBad ? (string) $firstBad : null;
    }

    private function lastSampleAt(object $e): ?string
    {
        $raw = DB::table('interface_stats')->where('device_id', $e->device_id)->where('if_index', $e->if_index)->max('created_at');
        $hourly = Schema::hasTable('interface_stats_hourly')
            ? DB::table('interface_stats_hourly')->where('device_id', $e->device_id)->where('if_index', $e->if_index)->max('bucket')
            : null;
        $last = max((string) $raw, (string) $hourly);

        return $last !== '' && $last > (string) $e->down_at ? $last : null;
    }

    private function downThreshold(): float
    {
        $v = Schema::hasTable('settings')
            ? DB::table('settings')->where('name', 'alert_rx_down_threshold')->value('value')
            : null;

        return is_numeric($v) ? (float) $v : -40.0;
    }

    /** @return int[]|null */
    private function snapshotIds(): ?array
    {
        $path = $this->option('ids');
        if (!$path) {
            return null;
        }
        $rows = json_decode((string) @file_get_contents($path), true);

        return is_array($rows) ? array_values(array_filter(array_map(fn ($r) => (int) ($r['id'] ?? 0), $rows))) : [];
    }

    private function human(int $sec): string
    {
        $d = intdiv($sec, 86400);
        $h = intdiv($sec % 86400, 3600);
        $m = intdiv($sec % 3600, 60);

        return ($d ? "{$d}h " : '') . ($h ? "{$h}j " : '') . "{$m}m";
    }
}
