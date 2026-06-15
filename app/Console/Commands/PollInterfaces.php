<?php

namespace App\Console\Commands;

use App\Services\InterfaceDiscovery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Polls active SNMP devices.
 *
 * Without --device it acts as an orchestrator: it fans out one
 * `poll:interfaces --device=X` subprocess per device and runs them in parallel
 * (capped by --concurrency), so a slow/unreachable device no longer stalls the
 * whole cycle. With --device it polls that single device in-process (the
 * worker path used both by the orchestrator and for manual one-off polls).
 */
class PollInterfaces extends Command
{
    protected $signature = 'poll:interfaces
        {--device= : Poll only this device id (worker mode)}
        {--concurrency=8 : Max devices polled in parallel (orchestrator mode)}
        {--timeout=90 : Per-device subprocess timeout in seconds}
        {--sequential : Poll all devices in-process, one at a time (no subprocesses)}';

    protected $description = 'Poll interfaces for active SNMP devices and store stats';

    public function handle(InterfaceDiscovery $discovery): int
    {
        // Ensure the per-device alert-state directory exists up-front (in the
        // single parent process) so concurrent workers never race to create it.
        $stateDir = storage_path('app/alert_state');
        if (!is_dir($stateDir)) {
            @mkdir($stateDir, 0775, true);
        }

        $deviceOption = $this->option('device');

        // Worker mode: poll exactly one device in-process.
        if ($deviceOption !== null && $deviceOption !== '') {
            return $this->pollOne($discovery, (int) $deviceOption);
        }

        $deviceIds = DB::table('snmp_devices')
            ->where('is_active', 1)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($deviceIds)) {
            $this->info('No active devices to poll.');
            return self::SUCCESS;
        }

        if ($this->option('sequential')) {
            return $this->pollSequential($discovery, $deviceIds);
        }

        return $this->pollParallel($deviceIds, max(1, (int) $this->option('concurrency')), max(5, (int) $this->option('timeout')));
    }

    /** Poll a single device in-process. */
    private function pollOne(InterfaceDiscovery $discovery, int $deviceId): int
    {
        $result = $discovery->discover($deviceId, true);
        if (!($result['success'] ?? false)) {
            $this->error("Device {$deviceId}: " . ($result['error'] ?? 'failed'));
            return self::FAILURE;
        }
        $this->info("Polled device ID: {$deviceId}");
        return self::SUCCESS;
    }

    /** Legacy in-process sequential loop (fallback / debugging). */
    private function pollSequential(InterfaceDiscovery $discovery, array $deviceIds): int
    {
        foreach ($deviceIds as $id) {
            $this->pollOne($discovery, $id);
        }
        return self::SUCCESS;
    }

    /**
     * Fan out one subprocess per device, keeping at most $concurrency running.
     * A hung device only occupies its own slot (killed at $timeout) and never
     * blocks the others.
     */
    private function pollParallel(array $deviceIds, int $concurrency, int $timeout): int
    {
        $phpBinary = PHP_BINARY ?: 'php';
        $artisan = base_path('artisan');

        $queue = $deviceIds;
        $running = []; // deviceId => Process
        $ok = 0;
        $fail = 0;

        while ($queue || $running) {
            // Fill free slots.
            while (count($running) < $concurrency && $queue) {
                $id = array_shift($queue);
                $proc = new Process([$phpBinary, $artisan, 'poll:interfaces', '--device=' . $id], base_path());
                $proc->setTimeout($timeout);
                try {
                    $proc->start();
                    $running[$id] = $proc;
                } catch (\Throwable $e) {
                    $this->error("Device {$id}: spawn failed — " . $e->getMessage());
                    $fail++;
                }
            }

            // Reap finished / timed-out processes.
            foreach ($running as $id => $proc) {
                try {
                    if ($proc->isRunning()) {
                        $proc->checkTimeout(); // kills + throws if over the limit
                        continue;
                    }
                } catch (ProcessTimedOutException $e) {
                    $this->error("Device {$id}: timed out after {$timeout}s");
                    $fail++;
                    unset($running[$id]);
                    continue;
                }

                unset($running[$id]);
                if ($proc->isSuccessful()) {
                    $ok++;
                } else {
                    $fail++;
                    $err = trim($proc->getErrorOutput() ?: $proc->getOutput());
                    $this->error("Device {$id}: " . ($err !== '' ? substr($err, 0, 200) : 'exit ' . $proc->getExitCode()));
                }
            }

            if ($running) {
                usleep(150_000);
            }
        }

        $this->info("Parallel poll done: {$ok} ok, {$fail} failed (concurrency {$concurrency}, timeout {$timeout}s).");
        return self::SUCCESS;
    }
}
