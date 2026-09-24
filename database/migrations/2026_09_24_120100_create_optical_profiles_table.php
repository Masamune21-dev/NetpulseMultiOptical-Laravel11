<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Profil OID optik buatan admin untuk vendor yang belum punya driver bawaan.
 *
 * Dua templat diisi NONAKTIF. OID-nya dicocokkan dengan teks MIB resmi (24 Sep 2026):
 *   - JUNIPER-DOM-MIB jnxDomCurrentEntry (INDEX ifIndex): RxLaserPower .5, TxLaserOutputPower .7,
 *     UNITS "0.01 dbm"; jnxDomMibRoot = jnxMibs 60 = 1.3.6.1.4.1.2636.3.60.
 *   - HH3C-TRANSCEIVER-INFO-MIB hh3cTransceiverInfoEntry (INDEX ifIndex): CurTXPower .9,
 *     CurRXPower .12, "hundredths of dBm"; hh3cTransceiver = hh3cCommon 70 = 1.3.6.1.4.1.25506.2.70.
 * Belum pernah diuji di perangkat BMKV — admin wajib menjalankan "Uji" sebelum mengaktifkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('optical_profiles')) {
            return;
        }
        Schema::create('optical_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('match_sys_object_id', 255)->nullable();
            $table->string('match_sys_descr', 255)->nullable();
            $table->string('rx_oid', 255);
            $table->string('tx_oid', 255)->nullable();
            $table->string('index_type', 20)->default('if_index');
            $table->string('value_unit', 20);
            $table->text('invalid_values')->nullable();
            $table->boolean('is_template')->default(false);
            $table->boolean('is_active')->default(false);
            $table->timestamp('tested_at')->nullable();
            $table->unsignedBigInteger('tested_device_id')->nullable();
            $table->text('test_summary')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('optical_profiles')->insert([
            [
                'name' => 'Juniper Junos (JUNIPER-DOM-MIB)',
                'match_sys_object_id' => '1.3.6.1.4.1.2636',
                'match_sys_descr' => null,
                'rx_oid' => '1.3.6.1.4.1.2636.3.60.1.1.1.1.5',
                'tx_oid' => '1.3.6.1.4.1.2636.3.60.1.1.1.1.7',
                'index_type' => 'if_index',
                'value_unit' => 'dbm_0_01',
                'invalid_values' => null,
                'is_template' => true,
                'is_active' => false,
                'notes' => 'Templat — belum diuji di perangkat BMKV. Jalankan Uji dulu.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'H3C / HPE Comware (HH3C-TRANSCEIVER-INFO-MIB)',
                'match_sys_object_id' => '1.3.6.1.4.1.25506',
                'match_sys_descr' => null,
                'rx_oid' => '1.3.6.1.4.1.25506.2.70.1.1.1.12',
                'tx_oid' => '1.3.6.1.4.1.25506.2.70.1.1.1.9',
                'index_type' => 'if_index',
                'value_unit' => 'dbm_0_01',
                'invalid_values' => null,
                'is_template' => true,
                'is_active' => false,
                'notes' => 'Templat — belum diuji di perangkat BMKV. Jalankan Uji dulu.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('optical_profiles');
    }
};
