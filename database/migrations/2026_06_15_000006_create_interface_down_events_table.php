<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per interface down event for SLA/uptime reporting: when it went down,
 * when it came back, and how long it was down. up_at NULL = still down.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('interface_down_events')) {
            return;
        }

        Schema::create('interface_down_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('device_id');
            $table->unsignedInteger('if_index');

            // Snapshots so the report stays readable even if the interface is
            // later renamed or removed.
            $table->string('if_name', 190)->nullable();
            $table->string('if_alias', 190)->nullable();
            $table->string('device_name', 190)->nullable();

            $table->dateTime('down_at');
            $table->dateTime('up_at')->nullable();
            $table->unsignedInteger('duration_sec')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['device_id', 'if_index', 'down_at'], 'idx_down_dev_if_time');
            $table->index('down_at', 'idx_down_at');
            // Fast lookup of currently-open events per interface.
            $table->index(['device_id', 'if_index', 'up_at'], 'idx_down_open');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interface_down_events');
    }
};
