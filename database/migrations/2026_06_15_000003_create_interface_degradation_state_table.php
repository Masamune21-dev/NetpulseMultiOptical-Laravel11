<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-interface state for optical degradation detection, so the daily check
 * alerts once when an SFP's RX starts drifting down from its baseline and only
 * re-alerts if it worsens (instead of every day), plus a recovery transition.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('interface_degradation_state')) {
            return;
        }

        Schema::create('interface_degradation_state', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('device_id');
            $table->unsignedInteger('if_index');

            // 'ok' or 'degraded'
            $table->string('status', 16)->default('ok');
            $table->decimal('baseline_dbm', 7, 3)->nullable();
            $table->decimal('recent_dbm', 7, 3)->nullable();
            $table->decimal('drop_db', 7, 3)->nullable();
            $table->timestamp('alerted_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['device_id', 'if_index'], 'uniq_degr_dev_if');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interface_degradation_state');
    }
};
