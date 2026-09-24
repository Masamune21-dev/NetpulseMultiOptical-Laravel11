<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Optical\CustomProfileDriver;
use App\Services\Optical\EntitySensorDriver;
use App\Services\Optical\HuaweiDriver;
use App\Services\Optical\MikrotikDriver;
use App\Services\Optical\OpticalDriverResolver;
use App\Services\Optical\VendorDetector;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Pengaturan → Vendor & Optik: akses khusus admin, validasi profil (termasuk penjaga SSRF),
 * aturan aktivasi, override, dan urutan driver yang dipilih resolver.
 */
class OpticalProfilesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable();
            $table->string('role')->default('viewer');
            $table->boolean('is_active')->default(true);
        });
        // snmp_devices tabel lama (tidak dibuat migrasi) — cukup kolom yang dipakai di sini.
        Schema::create('snmp_devices', function (Blueprint $table) {
            $table->id();
            $table->string('device_name')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('community')->nullable();
            $table->boolean('is_active')->default(true);
        });
    }

    private function user(string $role): User
    {
        $id = DB::table('users')->insertGetId([
            'name' => $role, 'email' => $role . '@example.test', 'username' => $role . '-uji',
            'password' => Hash::make('kata-sandi-uji-123'), 'role' => $role, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return User::query()->findOrFail($id);
    }

    /** @var array<string,User> */
    private array $users = [];

    private function as(string $role): static
    {
        $u = $this->users[$role] ??= $this->user($role);

        return $this->withSession(['auth.logged_in' => true, 'auth.user' => ['id' => $u->id, 'username' => $u->username, 'role' => $role]]);
    }

    private function device(string $name = 'SW-UJI-01', string $community = ''): int
    {
        return DB::table('snmp_devices')->insertGetId(['device_name' => $name, 'ip_address' => '192.0.2.10', 'community' => $community, 'is_active' => 1]);
    }

    private function validProfile(array $over = []): array
    {
        return array_merge([
            'name' => 'Switch ACME', 'match_sys_object_id' => '1.3.6.1.4.1.99999',
            'rx_oid' => '1.3.6.1.4.1.99999.1.2.3.1.5', 'tx_oid' => '1.3.6.1.4.1.99999.1.2.3.1.7',
            'index_type' => 'if_index', 'value_unit' => 'dbm_0_01', 'invalid_values' => '-4000',
        ], $over);
    }

    public function test_templates_are_seeded_inactive_and_untested(): void
    {
        $rows = DB::table('optical_profiles')->where('is_template', true)->get();
        $this->assertCount(2, $rows);
        foreach ($rows as $r) {
            $this->assertFalse((bool) $r->is_active);
            $this->assertNull($r->tested_at);
            $this->assertTrue(CustomProfileDriver::isValidOid($r->rx_oid));
        }
    }

    public function test_non_admin_is_forbidden_everywhere(): void
    {
        foreach (['technician', 'viewer'] as $role) {
            $this->as($role)->getJson('/api/optical/profiles')->assertForbidden();
            $this->as($role)->postJson('/api/optical/profiles', $this->validProfile())->assertForbidden();
            $this->as($role)->postJson('/api/optical/profiles/1/test', ['device_id' => 1])->assertForbidden();
            $this->as($role)->postJson('/api/optical/devices/1/override', ['driver' => 'none'])->assertForbidden();
        }
        $guest = $this->flushSession()->getJson('/api/optical/profiles');
        $this->assertContains($guest->status(), [302, 401], 'tamu harus ditolak');
    }

    public function test_admin_lists_devices_and_profiles(): void
    {
        $this->device();
        $this->as('admin')->getJson('/api/optical/profiles')
            ->assertOk()->assertJsonPath('success', true)
            ->assertJsonCount(1, 'devices')->assertJsonCount(2, 'profiles');
    }

    public function test_profile_validation_rejects_unsafe_input(): void
    {
        $admin = $this->as('admin');
        $admin->postJson('/api/optical/profiles', $this->validProfile(['rx_oid' => '1.3.6.1.2.1']))
            ->assertStatus(422)->assertJsonStructure(['errors' => ['rx_oid']]);
        $admin->postJson('/api/optical/profiles', $this->validProfile(['tx_oid' => 'ifDescr']))
            ->assertStatus(422)->assertJsonStructure(['errors' => ['tx_oid']]);
        $admin->postJson('/api/optical/profiles', $this->validProfile(['match_sys_descr' => '(unclosed']))
            ->assertStatus(422)->assertJsonStructure(['errors' => ['match_sys_descr']]);
        $admin->postJson('/api/optical/profiles', $this->validProfile(['value_unit' => 'kelvin']))->assertStatus(422);
        $admin->postJson('/api/optical/profiles', $this->validProfile(['invalid_values' => '1, dua']))->assertStatus(422);
    }

    public function test_host_in_request_is_ignored_test_requires_registered_device(): void
    {
        // SSRF: tidak ada parameter host; perangkat tak terdaftar ditolak sebelum SNMP apa pun.
        $id = $this->as('admin')->postJson('/api/optical/profiles', $this->validProfile())->assertOk()->json('id');
        $this->as('admin')->postJson("/api/optical/profiles/{$id}/test", ['device_id' => 999, 'ip_address' => '198.51.100.7'])
            ->assertNotFound();
        $dev = $this->device('SW-UJI-02', '');
        $this->as('admin')->postJson("/api/optical/profiles/{$id}/test", ['device_id' => $dev, 'ip_address' => '198.51.100.7'])
            ->assertStatus(422); // community kosong → berhenti sebelum SNMP
    }

    public function test_profile_cannot_be_activated_before_test_and_edit_resets(): void
    {
        $id = $this->as('admin')->postJson('/api/optical/profiles', $this->validProfile())->json('id');
        $this->as('admin')->postJson("/api/optical/profiles/{$id}/activate", ['active' => true])->assertStatus(422);

        DB::table('optical_profiles')->where('id', $id)->update(['tested_at' => now(), 'tested_device_id' => 1]);
        $this->as('admin')->postJson("/api/optical/profiles/{$id}/activate", ['active' => true])->assertOk();
        $this->assertTrue((bool) DB::table('optical_profiles')->where('id', $id)->value('is_active'));

        $this->as('admin')->postJson('/api/optical/profiles', $this->validProfile(['id' => $id, 'name' => 'Diubah']))->assertOk();
        $row = DB::table('optical_profiles')->find($id);
        $this->assertFalse((bool) $row->is_active);
        $this->assertNull($row->tested_at);
    }

    public function test_override_accepts_only_known_drivers_or_tested_profiles(): void
    {
        $dev = $this->device();
        $this->as('admin')->postJson("/api/optical/devices/{$dev}/override", ['driver' => 'huawei'])->assertOk();
        $this->assertSame('huawei', DB::table('optical_device_vendors')->where('device_id', $dev)->value('driver_override'));

        $this->as('admin')->postJson("/api/optical/devices/{$dev}/override", ['driver' => 'rm -rf'])->assertStatus(422);
        $untested = DB::table('optical_profiles')->value('id');
        $this->as('admin')->postJson("/api/optical/devices/{$dev}/override", ['driver' => "profile:{$untested}"])->assertStatus(422);

        $this->as('admin')->postJson("/api/optical/devices/{$dev}/override", ['driver' => 'auto'])->assertOk();
        $this->assertNull(DB::table('optical_device_vendors')->where('device_id', $dev)->value('driver_override'));
    }

    public function test_resolver_driver_order(): void
    {
        $r = app(OpticalDriverResolver::class);
        $dev = (object) ['id' => 1, 'device_name' => 'SW-UJI'];
        $keys = fn (array $info) => array_map(fn ($d) => $d->key(), $r->driversFor($dev, $info));

        $this->assertSame(['mikrotik'], $keys(['vendor' => 'mikrotik']));
        $this->assertSame(['huawei'], $keys(['vendor' => 'huawei']));
        $this->assertSame(['entity_sensor'], $keys(['vendor' => 'cisco']));
        $this->assertSame(['mikrotik'], $keys(['vendor' => 'unknown']));
        $this->assertSame(['mikrotik', 'huawei'], array_map(fn ($d) => $d->key(),
            $r->driversFor((object) ['id' => 2, 'device_name' => 'SW-HUAWEI-UJI'], ['vendor' => 'unknown'])));
        $this->assertSame([], $keys(['vendor' => 'huawei', 'override' => 'none']));

        // Profil aktif + lulus uji yang cocok didahulukan sebelum driver bawaan.
        $pid = DB::table('optical_profiles')->where('name', 'like', 'Juniper%')->value('id');
        DB::table('optical_profiles')->where('id', $pid)->update(['is_active' => true, 'tested_at' => now()]);
        $r2 = app(OpticalDriverResolver::class);
        $this->assertSame(["profile:{$pid}", 'entity_sensor'], array_map(fn ($d) => $d->key(),
            $r2->driversFor($dev, ['vendor' => 'juniper', 'sys_object_id' => '1.3.6.1.4.1.2636.1.1.1.2.1'])));
    }

    public function test_vendor_detector_uses_cache_without_snmp(): void
    {
        DB::table('optical_device_vendors')->insert([
            'device_id' => 7, 'vendor' => 'huawei', 'sys_object_id' => '1.3.6.1.4.1.2011.2.23',
            'detected_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $snmp = new class('192.0.2.1', 'x') extends \App\Services\Optical\SnmpSession {
            public function get(string $oid): string|false
            {
                throw new \RuntimeException('tidak boleh menyentuh SNMP bila cache masih segar');
            }
        };
        $info = app(VendorDetector::class)->detect((object) ['id' => 7], $snmp);
        $this->assertSame('huawei', $info['vendor']);
    }
}
