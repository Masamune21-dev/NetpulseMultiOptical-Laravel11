<?php

namespace App\Console\Commands;

use App\Services\OltCollector;
use Illuminate\Console\Command;

class CollectOlt extends Command
{
    protected $signature = 'olt:collect {olt}';
    protected $description = 'Collect OLT data for a single OLT ID';

    public function handle(OltCollector $collector): int
    {
        $oltId = $this->argument('olt');
        $olts = config('olt');

        if (!isset($olts[$oltId])) {
            $this->error("Invalid OLT: {$oltId}");
            return Command::FAILURE;
        }

        $olt = $olts[$oltId];
        $base = storage_path('app/olt');
        $dir = $base . DIRECTORY_SEPARATOR . $oltId;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->info("Collecting {$olt['name']}...");

        try {
            // Pass ID into collector for debug dumps / file paths.
            $olt['_id'] = (string) $oltId;
            $all = $collector->collectAllPon($olt);
        } catch (\Throwable $e) {
            $this->error("{$olt['name']} error: {$e->getMessage()}");
            return Command::FAILURE;
        }

        foreach ($all as $pon => $data) {
            $safe = str_replace('/', '_', $pon);
            $tmp = "{$dir}/pon_{$safe}.json.tmp";
            $out = "{$dir}/pon_{$safe}.json";
            file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT));
            rename($tmp, $out);
        }

        file_put_contents(
            "{$dir}/meta.json",
            json_encode([
                'name' => $olt['name'],
                'last_poll' => date('Y-m-d H:i:s'),
                'pon_count' => count($all),
            ], JSON_PRETTY_PRINT)
        );

        $this->info("DONE {$olt['name']}");
        return Command::SUCCESS;
    }
}
