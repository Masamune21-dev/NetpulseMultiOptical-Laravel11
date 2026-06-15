<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tiered downsampling for optical (redaman) history.
 *
 * Raw per-minute samples live in `interface_stats` (kept ~30 days). These
 * rollup tables hold hourly (kept ~18 months) and daily (kept multi-year)
 * aggregates so long-range RX/TX/loss trends survive without storing every
 * minute. We keep min/avg/max per bucket so dips/spikes are never lost when
 * downsampling, plus `samples` for sample-weighted re-aggregation.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['interface_stats_hourly', 'interface_stats_daily'] as $name) {
            if (Schema::hasTable($name)) {
                continue;
            }

            Schema::create($name, function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('device_id');
                $table->unsignedInteger('if_index');
                // Start of the bucket (top of the hour / midnight of the day).
                $table->dateTime('bucket');

                $table->decimal('rx_min', 6, 2)->nullable();
                $table->decimal('rx_avg', 7, 3)->nullable();
                $table->decimal('rx_max', 6, 2)->nullable();

                $table->decimal('tx_min', 6, 2)->nullable();
                $table->decimal('tx_avg', 7, 3)->nullable();
                $table->decimal('tx_max', 6, 2)->nullable();

                $table->float('loss_min')->nullable();
                $table->float('loss_avg')->nullable();
                $table->float('loss_max')->nullable();

                $table->unsignedInteger('samples')->default(0);

                $table->unique(['device_id', 'if_index', 'bucket'], 'uniq_dev_if_bucket');
                $table->index(['device_id', 'if_index', 'bucket'], 'idx_dev_if_bucket');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('interface_stats_hourly');
        Schema::dropIfExists('interface_stats_daily');
    }
};
