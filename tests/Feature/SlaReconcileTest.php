<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * sla:reconcile — closes SLA down events that stayed open although the link came back.
 * Fixture data only (documentation IPs, fake names).
 */
class SlaReconcileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Legacy core tables are not created by the migrations.
        Schema::create('snmp_devices', function (Blueprint $t) {
            $t->id();
            $t->string('device_name');
            $t->string('ip_address');
        });
        Schema::create('interfaces', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('device_id');
            $t->integer('if_index');
            $t->string('if_name');
            $t->integer('oper_status')->default(1);
            $t->decimal('rx_power', 8, 3)->nullable();
        });
        if (!Schema::hasTable('interface_stats')) {
            Schema::create('interface_stats', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('device_id');
                $t->integer('if_index');
                $t->decimal('tx_power', 8, 3)->nullable();
                $t->decimal('rx_power', 8, 3)->nullable();
                $t->decimal('loss', 8, 3)->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }

        DB::table('snmp_devices')->insert(['id' => 1, 'device_name' => 'SW-UJI-1', 'ip_address' => '192.0.2.10']);
    }

    private function event(int $ifIndex, string $downAt): int
    {
        return DB::table('interface_down_events')->insertGetId([
            'device_id' => 1, 'if_index' => $ifIndex, 'if_name' => "sfp{$ifIndex}", 'if_alias' => '',
            'device_name' => 'SW-UJI-1', 'down_at' => $downAt, 'up_at' => null, 'duration_sec' => null,
            'created_at' => $downAt,
        ]);
    }

    private function iface(int $ifIndex, int $oper, ?float $rx): void
    {
        DB::table('interfaces')->insert(['device_id' => 1, 'if_index' => $ifIndex, 'if_name' => "sfp{$ifIndex}", 'oper_status' => $oper, 'rx_power' => $rx]);
    }

    private function sample(int $ifIndex, string $at, float $rx): void
    {
        DB::table('interface_stats')->insert(['device_id' => 1, 'if_index' => $ifIndex, 'rx_power' => $rx, 'created_at' => $at]);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $id = $this->event(1, '2026-09-08 03:45:00');
        $this->iface(1, 1, -12.0);
        $this->sample(1, '2026-09-08 03:46:00', -12.0);

        $this->artisan('sla:reconcile')->assertSuccessful();

        $this->assertNull(DB::table('interface_down_events')->where('id', $id)->value('up_at'));
    }

    public function test_closes_at_first_good_raw_sample_after_the_outage(): void
    {
        $id = $this->event(1, '2026-09-08 03:45:00');
        $this->iface(1, 1, -12.0);
        $this->sample(1, '2026-09-08 03:44:00', -12.0); // before the outage: ignored
        $this->sample(1, '2026-09-08 03:45:30', -40.0); // still down
        $this->sample(1, '2026-09-08 03:46:02', -12.1); // recovery

        $this->artisan('sla:reconcile', ['--apply' => true])->assertSuccessful();

        $row = DB::table('interface_down_events')->where('id', $id)->first();
        $this->assertSame('2026-09-08 03:46:02', (string) $row->up_at);
        $this->assertSame(62, (int) $row->duration_sec);
    }

    public function test_port_still_down_without_recovery_stays_open(): void
    {
        $id = $this->event(2, '2026-06-17 15:45:04');
        $this->iface(2, 2, -40.0);
        // Same hour as the outage, good samples from BEFORE it went down.
        DB::table('interface_stats_hourly')->insert([
            'device_id' => 1, 'if_index' => 2, 'bucket' => '2026-06-17 15:00:00',
            'rx_min' => -40, 'rx_avg' => -20, 'rx_max' => -12, 'tx_min' => 1, 'tx_avg' => 1, 'tx_max' => 1,
            'loss_min' => 0, 'loss_avg' => 0, 'loss_max' => 0, 'samples' => 60,
        ]);

        $this->artisan('sla:reconcile', ['--apply' => true])->assertSuccessful();

        $this->assertNull(DB::table('interface_down_events')->where('id', $id)->value('up_at'));
    }

    public function test_uses_the_hourly_rollup_from_the_next_hour_when_raw_data_is_gone(): void
    {
        $id = $this->event(3, '2026-07-29 10:37:26');
        $this->iface(3, 1, -12.9);
        DB::table('interface_stats_hourly')->insert([
            'device_id' => 1, 'if_index' => 3, 'bucket' => '2026-07-29 11:00:00',
            'rx_min' => -13, 'rx_avg' => -12.9, 'rx_max' => -12.8, 'tx_min' => 1, 'tx_avg' => 1, 'tx_max' => 1,
            'loss_min' => 0, 'loss_avg' => 0, 'loss_max' => 0, 'samples' => 60,
        ]);

        $this->artisan('sla:reconcile', ['--apply' => true])->assertSuccessful();

        $this->assertSame('2026-07-29 11:00:00', (string) DB::table('interface_down_events')->where('id', $id)->value('up_at'));
    }

    public function test_reopens_an_outage_that_started_after_the_missed_recovery(): void
    {
        $id = $this->event(4, '2026-09-10 11:08:27');
        $this->iface(4, 2, -40.0); // down right now
        $this->sample(4, '2026-09-10 11:09:04', -14.0); // recovered
        $this->sample(4, '2026-09-19 23:36:04', -14.0); // last good sample
        $this->sample(4, '2026-09-19 23:37:13', -40.0); // new outage starts

        $this->artisan('sla:reconcile', ['--apply' => true])->assertSuccessful();

        $this->assertSame('2026-09-10 11:09:04', (string) DB::table('interface_down_events')->where('id', $id)->value('up_at'));
        $open = DB::table('interface_down_events')->where('if_index', 4)->whereNull('up_at')->get();
        $this->assertCount(1, $open);
        $this->assertSame('2026-09-19 23:37:13', (string) $open[0]->down_at);
    }

    public function test_event_of_deleted_device_is_closed_at_its_last_sample(): void
    {
        $id = DB::table('interface_down_events')->insertGetId([
            'device_id' => 99, 'if_index' => 5, 'if_name' => 'sfp5', 'if_alias' => '', 'device_name' => 'SW-HILANG',
            'down_at' => '2026-07-15 16:15:11', 'up_at' => null, 'duration_sec' => null, 'created_at' => now(),
        ]);
        DB::table('interface_stats')->insert(['device_id' => 99, 'if_index' => 5, 'rx_power' => -40, 'created_at' => '2026-07-24 23:00:00']);

        $this->artisan('sla:reconcile', ['--apply' => true])->assertSuccessful();

        $this->assertSame('2026-07-24 23:00:00', (string) DB::table('interface_down_events')->where('id', $id)->value('up_at'));
    }
}
