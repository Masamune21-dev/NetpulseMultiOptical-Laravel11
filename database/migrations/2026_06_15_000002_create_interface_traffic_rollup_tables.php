<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tiered downsampling for interface traffic history.
 *
 * Raw per-minute rate samples live in `interface_traffic_stats` (kept ~30
 * days). These rollups hold hourly (~18 months) and daily (multi-year)
 * aggregates of the in/out bps rates. We keep min/avg/max per bucket so peak
 * utilisation is never lost when downsampling. The cumulative octet counters
 * are intentionally NOT aggregated (averaging a counter is meaningless) — only
 * the derived rates are rolled up.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['interface_traffic_hourly', 'interface_traffic_daily'] as $name) {
            if (Schema::hasTable($name)) {
                continue;
            }

            Schema::create($name, function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('device_id');
                $table->unsignedInteger('if_index');
                $table->dateTime('bucket');

                $table->unsignedBigInteger('in_rate_min')->nullable();
                $table->unsignedBigInteger('in_rate_avg')->nullable();
                $table->unsignedBigInteger('in_rate_max')->nullable();

                $table->unsignedBigInteger('out_rate_min')->nullable();
                $table->unsignedBigInteger('out_rate_avg')->nullable();
                $table->unsignedBigInteger('out_rate_max')->nullable();

                $table->unsignedInteger('samples')->default(0);

                $table->unique(['device_id', 'if_index', 'bucket'], 'uniq_traf_dev_if_bucket');
                $table->index(['device_id', 'if_index', 'bucket'], 'idx_traf_dev_if_bucket');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('interface_traffic_hourly');
        Schema::dropIfExists('interface_traffic_daily');
    }
};
