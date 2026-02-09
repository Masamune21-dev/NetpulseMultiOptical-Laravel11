<?php

namespace App\Console\Commands;

use App\Services\OltCollector;
use Illuminate\Console\Command;

class CollectAllOlt extends Command
{
    protected $signature = 'olt:collect-all';
    protected $description = 'Collect OLT data for all configured OLTs';

    public function handle(OltCollector $collector): int
    {
        $olts = config('olt');
        if (!is_array($olts) || empty($olts)) {
            $this->warn('No OLT configured');
            return Command::SUCCESS;
        }

        foreach ($olts as $oltId => $olt) {
            $this->call('olt:collect', ['olt' => $oltId]);
        }

        return Command::SUCCESS;
    }
}
