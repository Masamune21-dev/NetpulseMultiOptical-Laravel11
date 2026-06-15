<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Enforce tiered retention for optical history:
 *   - raw interface_stats        : keep N days   (default 30)
 *   - interface_stats_hourly     : keep M months (default 18)
 *   - interface_stats_daily      : kept indefinitely (multi-year)
 *
 *   php artisan stats:prune --dry-run     # show what would be deleted
 *   php artisan stats:prune               # delete in batches
 *
 * Safety: refuses to prune raw unless the hourly rollup already covers the
 * window being deleted, so backfill must have run first.
 */
class PruneStats extends Command
{
    protected $signature = 'stats:prune
        {--days=30 : Keep this many days of raw per-minute interface_stats}
        {--hourly-months=18 : Keep this many months of hourly rollup}
        {--batch=10000 : Rows deleted per batch}
        {--dry-run : Report what would be deleted without deleting}';

    protected $description = 'Prune raw/hourly optical stats per tiered retention policy';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $hourlyMonths = max(1, (int) $this->option('hourly-months'));
        $batch = max(1000, (int) $this->option('batch'));
        $dry = (bool) $this->option('dry-run');

        $rawCutoff = Carbon::now()->subDays($days);
        $hourlyCutoff = Carbon::now()->subMonths($hourlyMonths);

        // Each tier: [label, raw table, hourly rollup table]. Daily rollups are
        // kept long-term and not pruned here.
        $tiers = [
            ['optical', 'interface_stats', 'interface_stats_hourly'],
            ['traffic', 'interface_traffic_stats', 'interface_traffic_hourly'],
        ];

        foreach ($tiers as [$label, $rawTable, $hourlyTable]) {
            // Safety: only prune raw once its hourly rollup covers the oldest
            // raw data — otherwise backfill hasn't run and we'd lose history.
            $oldRaw = DB::table($rawTable)->where('created_at', '<', $rawCutoff)->exists();
            if ($oldRaw) {
                $hourlyOldest = DB::table($hourlyTable)->min('bucket');
                $rawOldest = DB::table($rawTable)->min('created_at');
                if ($hourlyOldest === null || Carbon::parse($hourlyOldest)->gt(Carbon::parse($rawOldest)->addDay())) {
                    $this->error("[{$label}] refusing to prune: hourly rollup does not cover the oldest raw data. Run `php artisan stats:rollup --backfill` first.");
                    continue;
                }
            }

            // Raw per-minute table.
            $rawToDelete = DB::table($rawTable)->where('created_at', '<', $rawCutoff)->count();
            $this->info("[{$label}] raw {$rawTable} older than {$rawCutoff} ({$days}d): {$rawToDelete} rows");
            if (!$dry && $rawToDelete > 0) {
                $deleted = $this->batchDelete($rawTable, 'created_at', $rawCutoff, $batch);
                $this->info("  deleted {$deleted} raw rows");
            }

            // Hourly rollup table.
            $hourlyToDelete = DB::table($hourlyTable)->where('bucket', '<', $hourlyCutoff)->count();
            $this->info("[{$label}] hourly {$hourlyTable} older than {$hourlyCutoff} ({$hourlyMonths}mo): {$hourlyToDelete} rows");
            if (!$dry && $hourlyToDelete > 0) {
                $deleted = $this->batchDelete($hourlyTable, 'bucket', $hourlyCutoff, $batch);
                $this->info("  deleted {$deleted} hourly rows");
            }
        }

        if ($dry) {
            $this->comment('Dry run — nothing deleted.');
        }

        return self::SUCCESS;
    }

    /**
     * Delete rows where $column < $cutoff, in small primary-key-ordered
     * batches.
     *
     * Deleting by the auto-increment `id` (not by $column directly) is far
     * cheaper here: `created_at`/`bucket` is not the leading column of the
     * table index, so a `WHERE created_at < ?` delete scans and gap-locks a
     * huge range and collides with the every-minute poll inserts, causing
     * "Lock wait timeout exceeded". We resolve the cutoff to a single upper
     * id once, then delete the lowest ids in tiny batches (PK access),
     * pausing between batches and retrying on transient lock contention so a
     * busy table never aborts the whole prune.
     */
    private function batchDelete(string $table, string $column, Carbon $cutoff, int $batch): int
    {
        // Highest id whose timestamp is still older than the cutoff. Rows
        // inserted after this (id > maxId) are recent and never touched.
        $maxId = DB::table($table)->where($column, '<', $cutoff)->max('id');
        if ($maxId === null) {
            return 0;
        }

        $total = 0;
        $retries = 0;
        while (true) {
            try {
                $deleted = DB::table($table)
                    ->where('id', '<=', $maxId)
                    ->orderBy('id')
                    ->limit($batch)
                    ->delete();
            } catch (\Illuminate\Database\QueryException $e) {
                // 1205 = lock wait timeout, 1213 = deadlock: back off and retry.
                if (in_array((int) ($e->errorInfo[1] ?? 0), [1205, 1213], true) && $retries < 20) {
                    $retries++;
                    usleep(500_000);
                    continue;
                }
                throw $e;
            }

            $total += $deleted;
            if ($deleted === 0) {
                break;
            }
            // Brief pause so concurrent poll inserts can interleave.
            usleep(50_000);
        }

        return $total;
    }
}
