<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-interface RX threshold overrides.
 *
 * RX warning/down thresholds are otherwise global (settings keys
 * alert_rx_warning_high/low, alert_rx_down_threshold). Long-haul vs short SFPs
 * have very different "normal" RX, so this lets an admin override any of the
 * three per interface. NULL on a column means "fall back to the global value".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('interface_thresholds')) {
            return;
        }

        Schema::create('interface_thresholds', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('device_id');
            $table->unsignedInteger('if_index');

            $table->decimal('rx_warn_high', 6, 2)->nullable();
            $table->decimal('rx_warn_low', 6, 2)->nullable();
            $table->decimal('rx_down_threshold', 6, 2)->nullable();

            $table->timestamp('updated_at')->nullable();

            $table->unique(['device_id', 'if_index'], 'uniq_thr_dev_if');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interface_thresholds');
    }
};
