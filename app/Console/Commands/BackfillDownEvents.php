<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reconstruct historical interface_down_events from the alert_logs history
 * (interface_down / interface_up rows), so the SLA report has data going back
 * before live recording started. Idempotent: closed events are de-duped by
 * (device_id, if_index, down_at); a trailing still-down event is only added if
 * no open event already exists for that interface.
 */
class BackfillDownEvents extends Command
{
    protected $signature = 'sla:backfill {--dry-run : Report counts without inserting}';
    protected $description = 'Backfill interface_down_events from alert_logs history';

    public function handle(): int
    {
        if (!Schema::hasTable('alert_logs') || !Schema::hasTable('interface_down_events')) {
            $this->error('Required tables missing (alert_logs / interface_down_events).');
            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');

        $rows = DB::table('alert_logs')
            ->whereIn('event_type', ['interface_down', 'interface_up'])
            ->orderBy('device_id')
            ->orderBy('if_index')
            ->orderBy('created_at')
            ->get(['event_type', 'device_id', 'device_name', 'if_index', 'if_name', 'if_alias', 'created_at']);

        // Group sequentially by interface.
        $byIface = [];
        foreach ($rows as $r) {
            $byIface[$r->device_id . ':' . $r->if_index][] = $r;
        }

        $inserted = 0;
        $skipped = 0;
        foreach ($byIface as $events) {
            $open = null; // ['down_at'=>, meta...]
            foreach ($events as $e) {
                if ($e->event_type === 'interface_down') {
                    if ($open === null) {
                        $open = $e;
                    }
                    // consecutive downs: keep the first
                } else { // interface_up
                    if ($open !== null) {
                        $this->emitEvent($open, $e->created_at, $dry, $inserted, $skipped);
                        $open = null;
                    }
                }
            }
            // Trailing unmatched down → still-down event.
            if ($open !== null) {
                $this->emitEvent($open, null, $dry, $inserted, $skipped);
            }
        }

        $this->info(($dry ? '[dry-run] ' : '') . "Backfill: {$inserted} event(s) inserted, {$skipped} skipped (already present).");
        return self::SUCCESS;
    }

    private function emitEvent($down, $upAt, bool $dry, int &$inserted, int &$skipped): void
    {
        $deviceId = (int) $down->device_id;
        $ifIndex = (int) $down->if_index;

        // Dedup closed events by (device, if, down_at).
        $exists = DB::table('interface_down_events')
            ->where('device_id', $deviceId)
            ->where('if_index', $ifIndex)
            ->where('down_at', $down->created_at)
            ->exists();
        if ($exists) {
            $skipped++;
            return;
        }

        // For a still-open event, don't add a duplicate if one is already open.
        if ($upAt === null) {
            $hasOpen = DB::table('interface_down_events')
                ->where('device_id', $deviceId)
                ->where('if_index', $ifIndex)
                ->whereNull('up_at')
                ->exists();
            if ($hasOpen) {
                $skipped++;
                return;
            }
        }

        $duration = $upAt !== null
            ? max(0, strtotime((string) $upAt) - strtotime((string) $down->created_at))
            : null;

        if ($dry) {
            $inserted++;
            return;
        }

        DB::table('interface_down_events')->insert([
            'device_id' => $deviceId,
            'if_index' => $ifIndex,
            'if_name' => $down->if_name,
            'if_alias' => $down->if_alias,
            'device_name' => $down->device_name,
            'down_at' => $down->created_at,
            'up_at' => $upAt,
            'duration_sec' => $duration,
            'created_at' => now(),
        ]);
        $inserted++;
    }
}
