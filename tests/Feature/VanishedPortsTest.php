<?php

namespace Tests\Feature;

use App\Services\InterfaceDiscovery;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Ports the device no longer reports are auto-retired by the poller; system-retired
 * ports come back on their own, admin-retired ones do not. Fixture data only.
 */
class VanishedPortsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('interfaces', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('device_id');
            $t->integer('if_index');
            $t->string('if_name');
            $t->boolean('is_monitored')->default(true);
            $t->timestamp('last_seen')->nullable();
        });
    }

    private function port(int $idx, string $lastSeen, bool $monitored = true): void
    {
        DB::table('interfaces')->insert(['device_id' => 1, 'if_index' => $idx, 'if_name' => "sfp{$idx}", 'is_monitored' => $monitored, 'last_seen' => $lastSeen]);
    }

    /** @param array<int,bool> $seen */
    private function reconcile(array $seen): void
    {
        $prev = DB::table('interfaces')->where('device_id', 1)->get(['if_index', 'is_monitored', 'last_seen']);
        $unmonitored = [];
        foreach ($prev as $r) {
            if ((int) $r->is_monitored === 0) {
                $unmonitored[(int) $r->if_index] = true;
            }
        }
        $m = new \ReflectionMethod(InterfaceDiscovery::class, 'reconcileVanishedPorts');
        $m->invoke(app(InterfaceDiscovery::class), 1, $seen, $prev, $unmonitored);
    }

    private function monitored(int $idx): int
    {
        return (int) DB::table('interfaces')->where('if_index', $idx)->value('is_monitored');
    }

    public function test_port_missing_for_more_than_a_day_is_retired_and_its_open_event_closed(): void
    {
        $this->port(1, now()->toDateTimeString());
        $this->port(9, now()->subMonths(6)->toDateTimeString()); // gone since March-like
        DB::table('interface_down_events')->insert([
            'device_id' => 1, 'if_index' => 9, 'if_name' => 'sfp9', 'if_alias' => '', 'device_name' => 'SW-UJI',
            'down_at' => now()->subMonths(6), 'up_at' => null, 'duration_sec' => null, 'created_at' => now(),
        ]);

        $this->reconcile([1 => true]);

        $this->assertSame(1, $this->monitored(1));
        $this->assertSame(0, $this->monitored(9));
        $this->assertSame('system', DB::table('interface_monitoring_changes')->where('if_index', 9)->value('changed_by'));
        $this->assertNotNull(DB::table('interface_down_events')->where('if_index', 9)->value('up_at'));
    }

    public function test_port_missing_only_briefly_is_left_alone(): void
    {
        $this->port(1, now()->toDateTimeString());
        $this->port(2, now()->subMinutes(10)->toDateTimeString()); // e.g. one partial walk

        $this->reconcile([1 => true]);

        $this->assertSame(1, $this->monitored(2));
        $this->assertSame(0, DB::table('interface_monitoring_changes')->count());
    }

    public function test_system_retired_port_is_re_enabled_when_reported_again(): void
    {
        $this->port(9, now()->toDateTimeString(), false);
        DB::table('interface_monitoring_changes')->insert([
            'device_id' => 1, 'if_index' => 9, 'is_monitored' => 0, 'reason' => 'auto', 'changed_by' => 'system', 'created_at' => now()->subDay(),
        ]);

        $this->reconcile([9 => true]);

        $this->assertSame(1, $this->monitored(9));
    }

    public function test_admin_retired_port_stays_retired_when_reported_again(): void
    {
        $this->port(9, now()->toDateTimeString(), false);
        DB::table('interface_monitoring_changes')->insert([
            'device_id' => 1, 'if_index' => 9, 'is_monitored' => 0, 'reason' => 'kabel dicabut', 'changed_by' => 'admin-uji', 'created_at' => now()->subDay(),
        ]);

        $this->reconcile([9 => true]);

        $this->assertSame(0, $this->monitored(9));
    }
}
