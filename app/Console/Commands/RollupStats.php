<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Downsample raw optical samples (interface_stats) into hourly and daily
 * aggregates so long-range redaman history survives after raw is pruned.
 *
 *   php artisan stats:rollup                 # recent buckets (scheduled use)
 *   php artisan stats:rollup --backfill      # rebuild all history from raw
 *   php artisan stats:rollup --from=2026-02-01 --to=2026-02-10
 *
 * Idempotent: each bucket is recomputed from source via upsert, so re-runs
 * and overlapping windows are safe.
 */
class RollupStats extends Command
{
    protected $signature = 'stats:rollup
        {--from= : Start datetime (inclusive), e.g. 2026-02-01 or "2026-02-01 00:00:00"}
        {--to= : End datetime (exclusive). Defaults to now}
        {--backfill : Process the entire raw history, day by day}';

    protected $description = 'Roll up raw interface_stats into hourly and daily aggregates';

    public function handle(): int
    {
        if ($this->option('backfill')) {
            return $this->backfill();
        }

        $to = $this->option('to') ? Carbon::parse($this->option('to')) : Carbon::now();

        if ($this->option('from')) {
            $from = Carbon::parse($this->option('from'));
        } else {
            // Scheduled default: recompute the last few hours so the
            // just-completed hour (and any late samples) are captured.
            $from = (clone $to)->subHours(3);
        }

        $hourly = $this->rollupHourly($from, $to);
        // Daily is derived from hourly; widen to cover whole affected days.
        $daily = $this->rollupDaily((clone $from)->startOfDay(), $to);

        $this->info("Rollup done: {$hourly} hourly bucket(s), {$daily} daily bucket(s) [{$from} → {$to}]");

        return self::SUCCESS;
    }

    private function backfill(): int
    {
        $span = DB::selectOne('SELECT MIN(created_at) mn, MAX(created_at) mx FROM interface_stats');
        if (!$span || !$span->mn) {
            $this->info('No raw data to backfill.');
            return self::SUCCESS;
        }

        $start = Carbon::parse($span->mn)->startOfDay();
        $end = Carbon::parse($span->mx);
        $this->info("Backfilling hourly from {$start} to {$end} (day by day)...");

        $cursor = clone $start;
        $totalHourly = 0;
        while ($cursor < $end) {
            $dayEnd = (clone $cursor)->addDay();
            $n = $this->rollupHourly($cursor, $dayEnd);
            $totalHourly += $n;
            $this->line("  {$cursor->toDateString()}: {$n} hourly buckets");
            $cursor = $dayEnd;
        }

        $this->info('Building daily aggregates from hourly...');
        $totalDaily = $this->rollupDaily($start, $end);

        $this->info("Backfill done: {$totalHourly} hourly, {$totalDaily} daily buckets.");
        return self::SUCCESS;
    }

    /**
     * Aggregate raw interface_stats into interface_stats_hourly for [from, to).
     * Returns number of buckets written.
     */
    private function rollupHourly(Carbon $from, Carbon $to): int
    {
        $sql = "
            INSERT INTO interface_stats_hourly
                (device_id, if_index, bucket,
                 rx_min, rx_avg, rx_max,
                 tx_min, tx_avg, tx_max,
                 loss_min, loss_avg, loss_max, samples)
            SELECT device_id, if_index,
                   DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') AS bucket,
                   MIN(rx_power), AVG(rx_power), MAX(rx_power),
                   MIN(tx_power), AVG(tx_power), MAX(tx_power),
                   MIN(loss), AVG(loss), MAX(loss),
                   COUNT(*)
            FROM interface_stats
            WHERE created_at >= ? AND created_at < ?
            GROUP BY device_id, if_index, bucket
            ON DUPLICATE KEY UPDATE
                rx_min=VALUES(rx_min), rx_avg=VALUES(rx_avg), rx_max=VALUES(rx_max),
                tx_min=VALUES(tx_min), tx_avg=VALUES(tx_avg), tx_max=VALUES(tx_max),
                loss_min=VALUES(loss_min), loss_avg=VALUES(loss_avg), loss_max=VALUES(loss_max),
                samples=VALUES(samples)
        ";

        return DB::affectingStatement($sql, [
            $from->format('Y-m-d H:i:s'),
            $to->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Aggregate interface_stats_hourly into interface_stats_daily for [from, to).
     * Averages are sample-weighted; min/max carried through so dips survive.
     * Returns number of buckets written.
     */
    private function rollupDaily(Carbon $from, Carbon $to): int
    {
        $sql = "
            INSERT INTO interface_stats_daily
                (device_id, if_index, bucket,
                 rx_min, rx_avg, rx_max,
                 tx_min, tx_avg, tx_max,
                 loss_min, loss_avg, loss_max, samples)
            SELECT device_id, if_index,
                   DATE(bucket) AS day,
                   MIN(rx_min),
                   SUM(rx_avg * samples) / NULLIF(SUM(CASE WHEN rx_avg IS NOT NULL THEN samples ELSE 0 END), 0),
                   MAX(rx_max),
                   MIN(tx_min),
                   SUM(CASE WHEN tx_avg IS NOT NULL THEN tx_avg * samples ELSE 0 END)
                     / NULLIF(SUM(CASE WHEN tx_avg IS NOT NULL THEN samples ELSE 0 END), 0),
                   MAX(tx_max),
                   MIN(loss_min),
                   SUM(CASE WHEN loss_avg IS NOT NULL THEN loss_avg * samples ELSE 0 END)
                     / NULLIF(SUM(CASE WHEN loss_avg IS NOT NULL THEN samples ELSE 0 END), 0),
                   MAX(loss_max),
                   SUM(samples)
            FROM interface_stats_hourly
            WHERE bucket >= ? AND bucket < ?
            GROUP BY device_id, if_index, day
            ON DUPLICATE KEY UPDATE
                rx_min=VALUES(rx_min), rx_avg=VALUES(rx_avg), rx_max=VALUES(rx_max),
                tx_min=VALUES(tx_min), tx_avg=VALUES(tx_avg), tx_max=VALUES(tx_max),
                loss_min=VALUES(loss_min), loss_avg=VALUES(loss_avg), loss_max=VALUES(loss_max),
                samples=VALUES(samples)
        ";

        return DB::affectingStatement($sql, [
            $from->format('Y-m-d H:i:s'),
            $to->format('Y-m-d H:i:s'),
        ]);
    }
}
