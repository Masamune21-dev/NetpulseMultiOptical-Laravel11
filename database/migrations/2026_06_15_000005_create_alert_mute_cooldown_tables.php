<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maintenance mute + flap/rate cooldown for alerting.
 *
 * alert_mutes:    suppress alerts for a device (or globally, device_id = 0)
 *                 during maintenance. muted_until = optional auto-expiry.
 * alert_cooldowns: last time an alert of a given collapsed category fired for a
 *                 device/interface, so flapping links don't spam every poll.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('alert_mutes')) {
            Schema::create('alert_mutes', function (Blueprint $table) {
                $table->bigIncrements('id');
                // 0 = global mute (all devices); otherwise the snmp_devices id.
                $table->unsignedInteger('device_id')->default(0);
                $table->string('note', 190)->nullable();
                // NULL = muted until manually cleared; otherwise auto-expires.
                $table->dateTime('muted_until')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->unique('device_id', 'uniq_mute_device');
            });
        }

        if (!Schema::hasTable('alert_cooldowns')) {
            Schema::create('alert_cooldowns', function (Blueprint $table) {
                $table->string('k', 191)->primary();
                $table->dateTime('last_sent_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_cooldowns');
        Schema::dropIfExists('alert_mutes');
    }
};
