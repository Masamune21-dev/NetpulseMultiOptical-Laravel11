<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('interfaces')) {
            Schema::table('interfaces', function (Blueprint $table) {
                if (!Schema::hasColumn('interfaces', 'in_octets')) {
                    $table->unsignedBigInteger('in_octets')->nullable()->after('voltage');
                }
                if (!Schema::hasColumn('interfaces', 'out_octets')) {
                    $table->unsignedBigInteger('out_octets')->nullable()->after('in_octets');
                }
                if (!Schema::hasColumn('interfaces', 'in_rate_bps')) {
                    $table->unsignedBigInteger('in_rate_bps')->nullable()->after('out_octets');
                }
                if (!Schema::hasColumn('interfaces', 'out_rate_bps')) {
                    $table->unsignedBigInteger('out_rate_bps')->nullable()->after('in_rate_bps');
                }
                if (!Schema::hasColumn('interfaces', 'counters_polled_at')) {
                    $table->dateTime('counters_polled_at')->nullable()->after('out_rate_bps');
                }
            });
        }

        if (!Schema::hasTable('interface_traffic_stats')) {
            Schema::create('interface_traffic_stats', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('device_id')->index();
                $table->unsignedInteger('if_index');
                $table->unsignedBigInteger('in_octets')->nullable();
                $table->unsignedBigInteger('out_octets')->nullable();
                $table->unsignedBigInteger('in_rate_bps')->nullable();
                $table->unsignedBigInteger('out_rate_bps')->nullable();
                $table->dateTime('created_at')->useCurrent();

                $table->index(['device_id', 'if_index', 'created_at'], 'idx_traffic_dev_if_time');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('interface_traffic_stats');

        if (Schema::hasTable('interfaces')) {
            Schema::table('interfaces', function (Blueprint $table) {
                foreach (['counters_polled_at', 'out_rate_bps', 'in_rate_bps', 'out_octets', 'in_octets'] as $col) {
                    if (Schema::hasColumn('interfaces', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
