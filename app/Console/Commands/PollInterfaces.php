<?php

namespace App\Console\Commands;

use App\Services\InterfaceDiscovery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PollInterfaces extends Command
{
    protected $signature = 'poll:interfaces {--device=}';
    protected $description = 'Poll interfaces for active SNMP devices and store stats';

    public function handle(InterfaceDiscovery $discovery): int
    {
        $deviceOption = $this->option('device');
        $devices = DB::table('snmp_devices')
            ->select(['id'])
            ->where('is_active', 1);

        if ($deviceOption) {
            $devices->where('id', (int) $deviceOption);
        }

        $devices = $devices->get();

        foreach ($devices as $device) {
            $result = $discovery->discover((int) $device->id, true);
            if (!($result['success'] ?? false)) {
                $this->error("Device {$device->id}: " . ($result['error'] ?? 'failed'));
                continue;
            }
            $this->info("Polled device ID: {$device->id}");
        }

        return Command::SUCCESS;
    }
}
