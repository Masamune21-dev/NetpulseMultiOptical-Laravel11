<?php

namespace App\Services\Optical;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Memilih dan menjalankan driver optik untuk satu perangkat.
 *
 * Urutan:
 *   1. override admin per perangkat (optical_device_vendors.driver_override), bila ada;
 *   2. profil OID aktif yang cocok dengan sysObjectID/sysDescr perangkat;
 *   3. driver bawaan menurut vendor: mikrotik → MikrotikDriver, huawei → HuaweiDriver,
 *      vendor lain yang terdeteksi → EntitySensorDriver (standar ENTITY-SENSOR-MIB);
 *   4. vendor tidak terdeteksi (SNMP sistem tak menjawab) → PERILAKU LAMA persis:
 *      MikroTik untuk semua perangkat + Huawei bila nama perangkat memuat
 *      huawei/quidway/cloudengine. Menjamin tidak ada perangkat yang kehilangan bacaan.
 *
 * Hasil beberapa driver digabung per ifName: driver yang lebih dulu menang.
 */
final class OpticalDriverResolver
{
    public const PROFILE_TABLE = 'optical_profiles';

    /** Pilihan override yang sah (selain "profile:<id>"). */
    public const OVERRIDES = [
        'auto' => 'Otomatis (deteksi vendor)',
        'mikrotik' => 'MikroTik RouterOS',
        'huawei' => 'Huawei VRP',
        'entity_sensor' => 'ENTITY-SENSOR-MIB (standar)',
        'none' => 'Jangan baca optik',
    ];

    private ?\Illuminate\Support\Collection $profileCache = null;

    public function __construct(private VendorDetector $detector)
    {
    }

    /**
     * @param  array<string,int>  $ifNameMap
     * @return array{optics: array<string,array{rx:?float,tx:?float}>, drivers: list<string>, vendor: string}
     */
    public function read(object $device, SnmpSession $snmp, array $ifNameMap): array
    {
        try {
            $info = $this->detector->detect($device, $snmp);
            $drivers = $this->driversFor($device, $info);
        } catch (\Throwable $e) {
            Log::warning('Optical resolver fallback ke jalur lama', ['device_id' => $device->id, 'error' => $e->getMessage()]);
            $info = ['vendor' => 'unknown'];
            $drivers = $this->legacyDrivers($device);
        }

        $optics = [];
        $used = [];
        foreach ($drivers as $driver) {
            try {
                $map = $driver->read($snmp, $ifNameMap);
            } catch (\Throwable $e) {
                Log::warning('Driver optik gagal', ['device_id' => $device->id, 'driver' => $driver->key(), 'error' => $e->getMessage()]);
                continue;
            }
            $used[] = $driver->key();
            foreach ($map as $ifName => $row) {
                $optics[$ifName] ??= $row;
            }
        }

        return ['optics' => $optics, 'drivers' => $used, 'vendor' => $info['vendor']];
    }

    /** @return list<OpticalDriver> */
    public function driversFor(object $device, array $info): array
    {
        $override = $info['override'] ?? null;
        if ($override && $override !== 'auto') {
            return $this->fromKey($override);
        }

        $drivers = array_map(fn ($p) => new CustomProfileDriver($p), $this->matchingProfiles($info));

        return array_merge($drivers, match ($info['vendor']) {
            'mikrotik' => [new MikrotikDriver()],
            'huawei' => [new HuaweiDriver()],
            'unknown' => $this->legacyDrivers($device),
            default => [new EntitySensorDriver()],
        });
    }

    /** @return list<OpticalDriver> */
    public function fromKey(string $key): array
    {
        if (str_starts_with($key, 'profile:')) {
            $profile = $this->profiles()->firstWhere('id', (int) substr($key, 8));

            return $profile ? [new CustomProfileDriver($profile)] : [];
        }

        return match ($key) {
            'mikrotik' => [new MikrotikDriver()],
            'huawei' => [new HuaweiDriver()],
            'entity_sensor' => [new EntitySensorDriver()],
            default => [],
        };
    }

    /** @return list<OpticalDriver> */
    public function legacyDrivers(object $device): array
    {
        $drivers = [new MikrotikDriver()];
        $name = (string) ($device->device_name ?? '');
        if (preg_match('/huawei|quidway|cloudengine/i', $name)) {
            $drivers[] = new HuaweiDriver();
        }

        return $drivers;
    }

    /** Profil aktif & lulus uji yang cocok dengan perangkat. */
    public function matchingProfiles(array $info): array
    {
        return $this->profiles()
            ->filter(fn ($p) => (bool) $p->is_active && $p->tested_at !== null && self::profileMatches($p, $info))
            ->values()
            ->all();
    }

    public static function profileMatches(object $profile, array $info): bool
    {
        $prefix = trim((string) ($profile->match_sys_object_id ?? ''), '.');
        $regex = (string) ($profile->match_sys_descr ?? '');
        if ($prefix === '' && $regex === '') {
            return false; // profil tanpa syarat hanya boleh dipakai lewat override eksplisit
        }
        $oid = trim((string) ($info['sys_object_id'] ?? ''), '.');
        if ($prefix !== '' && !($oid === $prefix || str_starts_with($oid, $prefix . '.'))) {
            return false;
        }
        if ($regex !== '' && @preg_match(self::delimit($regex), (string) ($info['sys_descr'] ?? '')) !== 1) {
            return false;
        }

        return true;
    }

    /** Pola sysDescr ditulis tanpa pembatas; dibungkus aman & tidak peka huruf besar. */
    public static function delimit(string $regex): string
    {
        return '~' . str_replace('~', '\~', $regex) . '~i';
    }

    private function profiles(): \Illuminate\Support\Collection
    {
        return $this->profileCache ??= Schema::hasTable(self::PROFILE_TABLE)
            ? DB::table(self::PROFILE_TABLE)->orderBy('id')->get()
            : collect();
    }
}
