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

            // Nama indeks di SQLite berlaku per DATABASE (di MariaDB per tabel), jadi nama yang
            // sama untuk dua tabel membuat migrate gagal di sqlite in-memory yang dipakai test.
            // Awalan nama tabel hanya dipasang di SQLite: MariaDB (produksi & instalasi baru)
            // tetap memakai nama lama persis, sehingga tidak ada skema produksi yang berubah.
            $prefix = Schema::getConnection()->getDriverName() === 'sqlite' ? $name . '_' : '';

            Schema::create($name, function (Blueprint $table) use ($prefix) {
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

                $table->unique(['device_id', 'if_index', 'bucket'], $prefix . 'uniq_dev_if_bucket');
                $table->index(['device_id', 'if_index', 'bucket'], $prefix . 'idx_dev_if_bucket');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('interface_stats_hourly');
        Schema::dropIfExists('interface_stats_daily');
    }
};
