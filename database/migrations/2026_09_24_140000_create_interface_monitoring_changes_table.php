<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat perubahan status pantau port ("tidak dipakai" / "pantau lagi").
 * Status yang berlaku tetap di kolom lama `interfaces.is_monitored`; tabel ini
 * menyimpan siapa, kapan, dan alasannya — alasan terakhir ditampilkan di UI.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('interface_monitoring_changes')) {
            return;
        }

        Schema::create('interface_monitoring_changes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id');
            $table->integer('if_index');
            $table->boolean('is_monitored');
            $table->string('reason', 255)->nullable();
            $table->string('changed_by', 100)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['device_id', 'if_index', 'created_at'], 'imc_port_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interface_monitoring_changes');
    }
};
