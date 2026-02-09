<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('alert_logs')) {
            return;
        }

        Schema::create('alert_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->timestamp('created_at')->useCurrent();

            $table->string('event_type', 64)->index();   // interface_down|interface_up|interface_warning|device_down|device_up
            $table->string('severity', 16)->index();     // info|warning|critical

            $table->unsignedBigInteger('device_id')->nullable()->index();
            $table->string('device_name', 190)->nullable();
            $table->string('device_ip', 64)->nullable();

            $table->unsignedInteger('if_index')->nullable()->index();
            $table->string('if_name', 190)->nullable();
            $table->string('if_alias', 190)->nullable();

            $table->decimal('rx_power', 8, 3)->nullable();
            $table->decimal('tx_power', 8, 3)->nullable();

            $table->text('message');
            $table->json('context')->nullable();

            $table->string('fingerprint', 64)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_logs');
    }
};

