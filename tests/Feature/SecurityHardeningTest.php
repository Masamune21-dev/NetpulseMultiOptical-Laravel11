<?php

namespace Tests\Feature;

use App\Http\Controllers\SlaController;
use App\Models\DeviceToken;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Pengaman hasil audit repo publik (24 Sep 2026): S3, R1, R2, R3, R4, R5, R8, R10.
 *
 * Tabel `users` di produksi berasal dari skema lama (username, full_name, role, is_active),
 * sedangkan migrasi di repo masih bawaan Laravel (name, email). Kolom lama ditambahkan di
 * setUp supaya test berjalan terhadap bentuk tabel yang sama dengan produksi.
 */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'kata-sandi-uji-123';

    protected function setUp(): void
    {
        parent::setUp();

        // SecurityLog menulis ke storage_path('logs'): alihkan supaya log produksi tak tersentuh.
        $dir = sys_get_temp_dir() . '/netpulse-test-' . bin2hex(random_bytes(4));
        mkdir($dir . '/logs', 0700, true);
        $this->app->useStoragePath($dir);

        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable();
            $table->string('full_name')->nullable();
            $table->string('role')->default('viewer');
            $table->boolean('is_active')->default(true);
        });
    }

    private function makeUser(string $role, bool $active = true, string $username = null): User
    {
        $username ??= $role . '-' . bin2hex(random_bytes(3));
        $id = DB::table('users')->insertGetId([
            'name' => $username,
            'email' => $username . '@example.test',
            'username' => $username,
            'full_name' => ucfirst($role) . ' Uji',
            'password' => Hash::make(self::PASSWORD),
            'role' => $role,
            'is_active' => $active,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->findOrFail($id);
    }

    private function asWeb(User $user): static
    {
        return $this->withSession([
            'auth.logged_in' => true,
            'auth.user' => [
                'id' => $user->id,
                'username' => $user->username,
                'full_name' => $user->full_name,
                'role' => $user->role,
            ],
        ]);
    }

    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer ' . $user->createToken('uji')->plainTextToken];
    }

    // --- S3 -------------------------------------------------------------------

    public function test_push_test_mengabaikan_token_dari_klien_dan_hanya_ke_perangkat_sendiri(): void
    {
        $me = $this->makeUser('viewer');
        $other = $this->makeUser('technician');
        DeviceToken::query()->create(['user_id' => $me->id, 'token' => 'token-milik-saya', 'last_seen_at' => now()]);
        DeviceToken::query()->create(['user_id' => $other->id, 'token' => 'token-orang-lain', 'last_seen_at' => now()]);

        $this->mock(FcmService::class, function ($mock) {
            $mock->shouldReceive('sendToToken')
                ->once()
                ->withArgs(fn (...$args) => ($args['deviceToken'] ?? $args[0]) === 'token-milik-saya')
                ->andReturn(['ok' => true]);
        });

        $this->postJson('/api/v1/push/test', [
            'title' => 'Phishing',
            'body' => 'Klik tautan ini',
            'token' => 'token-orang-lain',
        ], $this->bearer($me))->assertOk();
    }

    // --- R1 -------------------------------------------------------------------

    public function test_token_fcm_milik_user_lain_yang_masih_login_tidak_bisa_dibajak(): void
    {
        $victim = $this->makeUser('technician');
        $victim->createToken('hp-korban');
        DeviceToken::query()->create(['user_id' => $victim->id, 'token' => 'fcm-korban', 'last_seen_at' => now()]);

        $attacker = $this->makeUser('viewer');

        $this->postJson('/api/v1/device-token', ['token' => 'fcm-korban'], $this->bearer($attacker))
            ->assertStatus(409);

        $this->assertSame($victim->id, (int) DeviceToken::query()->where('token', 'fcm-korban')->value('user_id'));
    }

    public function test_token_fcm_boleh_pindah_bila_pemilik_lama_sudah_logout_di_semua_perangkat(): void
    {
        $old = $this->makeUser('technician');
        DeviceToken::query()->create(['user_id' => $old->id, 'token' => 'fcm-hp-bersama', 'last_seen_at' => now()]);
        $new = $this->makeUser('technician');

        $this->postJson('/api/v1/device-token', ['token' => 'fcm-hp-bersama'], $this->bearer($new))->assertOk();

        $this->assertSame($new->id, (int) DeviceToken::query()->where('token', 'fcm-hp-bersama')->value('user_id'));
    }

    public function test_logout_api_melepas_token_fcm_perangkat_itu(): void
    {
        $user = $this->makeUser('technician');
        DeviceToken::query()->create(['user_id' => $user->id, 'token' => 'fcm-hp-ini', 'last_seen_at' => now()]);

        $this->postJson('/api/v1/auth/logout', ['fcm_token' => 'fcm-hp-ini'], $this->bearer($user))->assertOk();

        $this->assertDatabaseMissing('device_tokens', ['token' => 'fcm-hp-ini']);
    }

    // --- R2 & R10 ---------------------------------------------------------------

    public function test_ganti_kata_sandi_mencabut_token_api_user_itu(): void
    {
        $admin = $this->makeUser('admin');
        $target = $this->makeUser('technician');
        $target->createToken('hp');
        DeviceToken::query()->create(['user_id' => $target->id, 'token' => 'fcm-target', 'last_seen_at' => now()]);

        $this->asWeb($admin)->postJson('/api/users', [
            'id' => $target->id,
            'username' => $target->username,
            'full_name' => $target->full_name,
            'role' => 'technician',
            'is_active' => 1,
            'password' => 'sandi-baru-yang-panjang',
        ])->assertOk();

        $this->assertSame(0, $target->tokens()->count());
        $this->assertDatabaseMissing('device_tokens', ['token' => 'fcm-target']);
    }

    public function test_ubah_nama_saja_tidak_mencabut_token(): void
    {
        $admin = $this->makeUser('admin');
        $target = $this->makeUser('technician');
        $target->createToken('hp');

        $this->asWeb($admin)->postJson('/api/users', [
            'id' => $target->id,
            'username' => $target->username,
            'full_name' => 'Nama Baru',
            'role' => 'technician',
            'is_active' => 1,
        ])->assertOk();

        $this->assertSame(1, $target->tokens()->count());
    }

    public function test_peran_di_luar_daftar_dan_kata_sandi_pendek_ditolak(): void
    {
        $admin = $this->makeUser('admin');

        $this->asWeb($admin)->postJson('/api/users', [
            'username' => 'baru', 'full_name' => 'Baru', 'role' => 'superadmin', 'password' => 'cukup-panjang-123',
        ])->assertStatus(400)->assertJsonPath('error', 'Invalid role');

        $this->asWeb($admin)->postJson('/api/users', [
            'username' => 'baru', 'full_name' => 'Baru', 'role' => 'viewer', 'password' => 'pendek',
        ])->assertStatus(400);

        $this->assertDatabaseMissing('users', ['username' => 'baru']);
    }

    // --- R3 -------------------------------------------------------------------

    public function test_viewer_mendapat_data_dummy_di_api_interfaces(): void
    {
        $viewer = $this->makeUser('viewer');
        $headers = $this->bearer($viewer);

        $this->getJson('/api/v1/interfaces', $headers)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.device_name', 'RTR-CORE-DEMO');

        $this->getJson('/api/v1/interfaces/traffic-history?device_id=101&if_index=1&range=1d', $headers)
            ->assertOk()
            ->assertJsonPath('meta.device_name', 'RTR-CORE-DEMO');
    }

    // --- R4 -------------------------------------------------------------------

    public function test_discovery_hanya_untuk_peran_yang_dikenal(): void
    {
        $viewer = $this->makeUser('viewer');
        $this->asWeb($viewer)->postJson('/api/discover_interfaces', ['device_id' => 5])
            ->assertOk()
            ->assertJsonPath('inserted', 0);

        $unknown = $this->makeUser('tamu');
        $this->asWeb($unknown)->postJson('/api/discover_interfaces', ['device_id' => 5])
            ->assertForbidden();
    }

    // --- R5 -------------------------------------------------------------------

    public function test_akun_nonaktif_tidak_bisa_dienumerasi_tanpa_kata_sandi_benar(): void
    {
        $this->makeUser('technician', active: false, username: 'nonaktif');

        // Kata sandi salah: jawabannya sama persis dengan username yang tidak ada.
        $this->postJson('/api/v1/auth/login', ['username' => 'nonaktif', 'password' => 'salah'])
            ->assertStatus(401)->assertJsonPath('error', 'Invalid username or password');
        $this->postJson('/api/v1/auth/login', ['username' => 'tidak-ada', 'password' => 'salah'])
            ->assertStatus(401)->assertJsonPath('error', 'Invalid username or password');

        // Status nonaktif hanya diungkap kepada pemegang kata sandi yang benar.
        $this->postJson('/api/v1/auth/login', ['username' => 'nonaktif', 'password' => self::PASSWORD])
            ->assertStatus(403);

        $this->post('/login', ['username' => 'nonaktif', 'password' => 'salah'])
            ->assertSessionHasErrors(['username' => 'Invalid username or password']);
    }

    public function test_satu_ip_dibatasi_20_percobaan_login_per_menit_lintas_username(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            $this->postJson('/api/v1/auth/login', ['username' => "user{$i}", 'password' => 'salah'])
                ->assertStatus(401);
        }

        $this->postJson('/api/v1/auth/login', ['username' => 'user21', 'password' => 'salah'])
            ->assertStatus(429);
    }

    // --- R8 -------------------------------------------------------------------

    public function test_ekspor_csv_menetralkan_sel_berawalan_formula(): void
    {
        $method = new \ReflectionMethod(SlaController::class, 'putCsv');
        $controller = $this->app->make(SlaController::class);

        $out = fopen('php://memory', 'w+');
        $method->invoke($controller, $out, ['=HYPERLINK("http://x")', '+1', '-2', '@SUM(A1)', 'SW-CORE', 42, -3.5]);
        rewind($out);
        $row = str_getcsv(trim(stream_get_contents($out)));

        $this->assertSame(["'=HYPERLINK(\"http://x\")", "'+1", "'-2", "'@SUM(A1)", 'SW-CORE', '42', '-3.5'], $row);
    }
}
