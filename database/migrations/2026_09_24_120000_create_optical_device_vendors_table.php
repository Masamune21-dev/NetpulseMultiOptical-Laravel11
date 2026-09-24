<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hasil deteksi vendor per perangkat (sysObjectID + sysDescr) dan override driver optik
 * pilihan admin. Tabel baru — snmp_devices adalah tabel lama yang tidak dibuat migrasi,
 * jadi sengaja tidak ditambahi kolom.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('optical_device_vendors')) {
            return;
        }
        Schema::create('optical_device_vendors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id')->unique();
            $table->string('sys_object_id', 255)->nullable();
            $table->text('sys_descr')->nullable();
            $table->string('vendor', 32)->default('unknown');
            $table->string('driver_override', 64)->nullable();
            $table->timestamp('detected_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('optical_device_vendors');
    }
};
