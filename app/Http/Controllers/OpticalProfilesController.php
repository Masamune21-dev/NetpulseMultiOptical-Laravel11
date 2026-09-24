<?php

namespace App\Http\Controllers;

use App\Services\Optical\CustomProfileDriver;
use App\Services\Optical\EntityAliasMap;
use App\Services\Optical\OpticalDriverResolver;
use App\Services\Optical\OpticalUnits;
use App\Services\Optical\SnmpSession;
use App\Services\Optical\VendorDetector;
use App\Support\Secret;
use App\Support\UserState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Pengaturan → Vendor & Optik (khusus admin).
 *
 * Mengelola profil OID optik untuk vendor tanpa driver bawaan, menguji profil terhadap
 * perangkat yang SUDAH terdaftar (host & community dari snmp_devices — pengguna tidak pernah
 * memasok alamat), dan menyetel override driver per perangkat. Profil hanya bisa diaktifkan
 * setelah uji berhasil; menyunting profil membatalkan hasil uji dan menonaktifkannya.
 */
class OpticalProfilesController extends Controller
{
    private const MAX_PREVIEW_ROWS = 200;

    public function index(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessAdmin($request)) {
            return $deny;
        }

        $vendors = DB::table(VendorDetector::TABLE)->get()->keyBy('device_id');
        $devices = DB::table('snmp_devices')->orderBy('device_name')
            ->get(['id', 'device_name', 'ip_address', 'is_active'])
            ->map(function ($d) use ($vendors) {
                $v = $vendors->get($d->id);

                return [
                    'id' => (int) $d->id,
                    'device_name' => $d->device_name,
                    'ip_address' => $d->ip_address,
                    'is_active' => (int) $d->is_active === 1,
                    'vendor' => $v->vendor ?? 'unknown',
                    'sys_object_id' => $v->sys_object_id ?? null,
                    'sys_descr' => $v ? mb_substr((string) $v->sys_descr, 0, 160) : null,
                    'driver_override' => $v->driver_override ?? null,
                    'detected_at' => $v->detected_at ?? null,
                ];
            });

        $profiles = DB::table(OpticalDriverResolver::PROFILE_TABLE)->orderBy('id')->get()->map(fn ($p) => [
            'id' => (int) $p->id,
            'name' => $p->name,
            'match_sys_object_id' => $p->match_sys_object_id,
            'match_sys_descr' => $p->match_sys_descr,
            'rx_oid' => $p->rx_oid,
            'tx_oid' => $p->tx_oid,
            'index_type' => $p->index_type,
            'value_unit' => $p->value_unit,
            'invalid_values' => implode(', ', CustomProfileDriver::invalidValues($p->invalid_values)),
            'is_template' => (bool) $p->is_template,
            'is_active' => (bool) $p->is_active,
            'tested_at' => $p->tested_at,
            'tested_device_id' => $p->tested_device_id,
            'test_summary' => json_decode((string) $p->test_summary, true),
            'notes' => $p->notes,
        ]);

        return response()->json([
            'success' => true,
            'devices' => $devices,
            'profiles' => $profiles,
            'units' => OpticalUnits::UNITS,
            'index_types' => CustomProfileDriver::INDEX_TYPES,
            'overrides' => OpticalDriverResolver::OVERRIDES,
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        if ($deny = $this->denyUnlessAdmin($request)) {
            return $deny;
        }
        $data = $request->validate([
            'id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:100'],
            'match_sys_object_id' => ['nullable', 'string', 'max:255'],
            'match_sys_descr' => ['nullable', 'string', 'max:255'],
            'rx_oid' => ['required', 'string', 'max:255'],
            'tx_oid' => ['nullable', 'string', 'max:255'],
            'index_type' => ['required', 'in:' . implode(',', array_keys(CustomProfileDriver::INDEX_TYPES))],
            'value_unit' => ['required', 'in:' . implode(',', array_keys(OpticalUnits::UNITS))],
            'invalid_values' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $errors = [];
        foreach (['rx_oid', 'tx_oid'] as $f) {
            if (($data[$f] ?? '') !== '' && !CustomProfileDriver::isValidOid($data[$f])) {
                $errors[$f] = 'OID harus numerik bertitik, minimal 9 komponen (mis. 1.3.6.1.4.1.2636.3.60.1.1.1.1.5).';
            }
        }
        $prefix = trim((string) ($data['match_sys_object_id'] ?? ''));
        if ($prefix !== '' && !CustomProfileDriver::isValidOidPrefix($prefix)) {
            $errors['match_sys_object_id'] = 'Awalan sysObjectID harus numerik, minimal 1.3.6.1.4.1.<enterprise>.';
        }
        $regex = (string) ($data['match_sys_descr'] ?? '');
        if ($regex !== '' && @preg_match(OpticalDriverResolver::delimit($regex), '') === false) {
            $errors['match_sys_descr'] = 'Pola sysDescr bukan regex yang sah.';
        }
        $invalid = [];
        foreach (array_filter(array_map('trim', explode(',', (string) ($data['invalid_values'] ?? '')))) as $v) {
            if (!is_numeric($v)) {
                $errors['invalid_values'] = 'Nilai tak sah harus angka dipisah koma.';
                break;
            }
            $invalid[] = (float) $v;
        }
        if ($errors) {
            return response()->json(['success' => false, 'errors' => $errors], 422);
        }

        $row = [
            'name' => trim($data['name']),
            'match_sys_object_id' => $prefix !== '' ? ltrim($prefix, '.') : null,
            'match_sys_descr' => $regex !== '' ? $regex : null,
            'rx_oid' => ltrim($data['rx_oid'], '.'),
            'tx_oid' => ($data['tx_oid'] ?? '') !== '' ? ltrim($data['tx_oid'], '.') : null,
            'index_type' => $data['index_type'],
            'value_unit' => $data['value_unit'],
            'invalid_values' => $invalid ? json_encode($invalid) : null,
            'notes' => $data['notes'] ?? null,
            // Setiap perubahan membatalkan hasil uji: profil harus diuji ulang sebelum aktif.
            'is_active' => false,
            'tested_at' => null,
            'tested_device_id' => null,
            'test_summary' => null,
            'updated_at' => now(),
        ];

        if (!empty($data['id'])) {
            $updated = DB::table(OpticalDriverResolver::PROFILE_TABLE)->where('id', $data['id'])->update($row);
            if (!$updated) {
                return response()->json(['success' => false, 'error' => 'Profil tidak ditemukan'], 404);
            }
            $id = (int) $data['id'];
        } else {
            $id = DB::table(OpticalDriverResolver::PROFILE_TABLE)->insertGetId($row + ['created_at' => now()]);
        }

        return response()->json(['success' => true, 'id' => $id]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessAdmin($request)) {
            return $deny;
        }
        DB::table(OpticalDriverResolver::PROFILE_TABLE)->where('id', $id)->delete();
        // Override yang menunjuk profil ini kembali ke otomatis.
        DB::table(VendorDetector::TABLE)->where('driver_override', 'profile:' . $id)->update(['driver_override' => null]);

        return response()->json(['success' => true]);
    }

    /** Uji profil pada satu perangkat terdaftar; tampilkan pratinjau nilai mentah → dBm. */
    public function test(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessAdmin($request)) {
            return $deny;
        }
        $profile = DB::table(OpticalDriverResolver::PROFILE_TABLE)->where('id', $id)->first();
        if (!$profile) {
            return response()->json(['success' => false, 'error' => 'Profil tidak ditemukan'], 404);
        }
        $deviceId = (int) $request->input('device_id');
        $device = DB::table('snmp_devices')->where('id', $deviceId)->first();
        if (!$device) {
            return response()->json(['success' => false, 'error' => 'Perangkat tidak ditemukan'], 404);
        }
        if (!function_exists('snmp2_real_walk')) {
            return response()->json(['success' => false, 'error' => 'Ekstensi SNMP tidak terpasang'], 500);
        }
        $community = Secret::reveal($device->community);
        if (!$community) {
            return response()->json(['success' => false, 'error' => 'Community SNMP perangkat belum diisi'], 422);
        }

        $snmp = new SnmpSession((string) $device->ip_address, $community);
        $ifNameMap = $snmp->ifNameMap();
        if ($ifNameMap === null) {
            return response()->json(['success' => false, 'error' => 'Perangkat tidak menjawab SNMP (IF-MIB)'], 422);
        }
        $rx = $snmp->realWalk($profile->rx_oid);
        $tx = $profile->tx_oid ? $snmp->realWalk($profile->tx_oid) : [];
        $alias = $profile->index_type === 'ent_physical' ? EntityAliasMap::read($snmp) : [];

        $built = CustomProfileDriver::build($profile, $rx, $tx, $alias, $ifNameMap);
        $mapped = count(array_filter($built['optics'], fn ($r) => $r['rx'] !== null || $r['tx'] !== null));
        $ok = $mapped > 0;
        $summary = ['rows' => count($built['rows']), 'interfaces' => $mapped, 'device_name' => $device->device_name];

        DB::table(OpticalDriverResolver::PROFILE_TABLE)->where('id', $id)->update([
            'tested_at' => $ok ? now() : null,
            'tested_device_id' => $ok ? $deviceId : null,
            'test_summary' => json_encode($summary),
            'is_active' => $ok ? $profile->is_active : false,
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'ok' => $ok,
            'summary' => $summary,
            'rows' => array_slice($built['rows'], 0, self::MAX_PREVIEW_ROWS),
            'truncated' => count($built['rows']) > self::MAX_PREVIEW_ROWS,
        ]);
    }

    public function activate(Request $request, int $id): JsonResponse
    {
        if ($deny = $this->denyUnlessAdmin($request)) {
            return $deny;
        }
        $profile = DB::table(OpticalDriverResolver::PROFILE_TABLE)->where('id', $id)->first();
        if (!$profile) {
            return response()->json(['success' => false, 'error' => 'Profil tidak ditemukan'], 404);
        }
        $active = $request->boolean('active');
        if ($active && $profile->tested_at === null) {
            return response()->json(['success' => false, 'error' => 'Uji profil ini dulu pada satu perangkat sebelum diaktifkan.'], 422);
        }
        DB::table(OpticalDriverResolver::PROFILE_TABLE)->where('id', $id)->update(['is_active' => $active, 'updated_at' => now()]);

        return response()->json(['success' => true, 'active' => $active]);
    }

    public function override(Request $request, int $deviceId): JsonResponse
    {
        if ($deny = $this->denyUnlessAdmin($request)) {
            return $deny;
        }
        if (!DB::table('snmp_devices')->where('id', $deviceId)->exists()) {
            return response()->json(['success' => false, 'error' => 'Perangkat tidak ditemukan'], 404);
        }
        $driver = (string) $request->input('driver', 'auto');
        $valid = array_key_exists($driver, OpticalDriverResolver::OVERRIDES)
            || (preg_match('/^profile:(\d+)$/', $driver, $m)
                && DB::table(OpticalDriverResolver::PROFILE_TABLE)->where('id', (int) $m[1])->whereNotNull('tested_at')->exists());
        if (!$valid) {
            return response()->json(['success' => false, 'error' => 'Driver tidak dikenal atau profil belum lulus uji'], 422);
        }
        app(VendorDetector::class)->setOverride($deviceId, $driver === 'auto' ? null : $driver);

        return response()->json(['success' => true, 'driver' => $driver]);
    }

    public function redetect(Request $request, int $deviceId): JsonResponse
    {
        if ($deny = $this->denyUnlessAdmin($request)) {
            return $deny;
        }
        $device = DB::table('snmp_devices')->where('id', $deviceId)->first();
        if (!$device) {
            return response()->json(['success' => false, 'error' => 'Perangkat tidak ditemukan'], 404);
        }
        $community = Secret::reveal($device->community);
        if (!$community || !function_exists('snmp2_get')) {
            return response()->json(['success' => false, 'error' => 'SNMP tidak tersedia untuk perangkat ini'], 422);
        }
        $info = app(VendorDetector::class)->detect($device, new SnmpSession((string) $device->ip_address, $community), true);

        return response()->json(['success' => true, 'vendor' => $info['vendor'], 'sys_object_id' => $info['sys_object_id']]);
    }

    private function denyUnlessAdmin(Request $request): ?JsonResponse
    {
        $user = (array) $request->session()->get('auth.user', []);
        $state = UserState::fresh((int) ($user['id'] ?? 0));
        $role = $state['role'] ?? ($user['role'] ?? null);

        return $role === 'admin' ? null : response()->json(['success' => false, 'error' => 'Khusus admin'], 403);
    }
}
