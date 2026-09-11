<?php

use App\Support\Secret;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * NP-4 / NP-6: enkripsi at-rest snmp_devices.community & settings.bot_token
 * (idempoten — nilai yang sudah terenkripsi dilewati; pembaca memakai
 * Secret::reveal() dengan fallback plaintext), lebarkan kolom community,
 * dan beri expires_at (90 hari dari sekarang) pada token API v1 lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('snmp_devices') && Schema::hasColumn('snmp_devices', 'community')) {
            Schema::table('snmp_devices', function (Blueprint $table) {
                $table->text('community')->nullable()->change();
            });

            foreach (DB::table('snmp_devices')->select(['id', 'community'])->get() as $row) {
                $raw = (string) ($row->community ?? '');
                if ($raw === '' || Secret::isEncrypted($raw)) {
                    continue;
                }
                DB::table('snmp_devices')->where('id', $row->id)->update(['community' => Secret::encrypt($raw)]);
            }
        }

        if (Schema::hasTable('settings')) {
            $raw = (string) (DB::table('settings')->where('name', 'bot_token')->value('value') ?? '');
            if ($raw !== '' && !Secret::isEncrypted($raw)) {
                DB::table('settings')->where('name', 'bot_token')->update(['value' => Secret::encrypt($raw)]);
            }
        }

        if (Schema::hasTable('personal_access_tokens') && Schema::hasColumn('personal_access_tokens', 'expires_at')) {
            DB::table('personal_access_tokens')
                ->whereNull('expires_at')
                ->update(['expires_at' => now()->addDays(90)]);
        }
    }

    public function down(): void
    {
        // Data tetap terenkripsi (pembaca toleran); hanya kolom yang dikembalikan.
        if (Schema::hasTable('snmp_devices') && Schema::hasColumn('snmp_devices', 'community')) {
            Schema::table('snmp_devices', function (Blueprint $table) {
                $table->string('community', 500)->nullable()->change();
            });
        }
    }
};
