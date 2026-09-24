<?php

namespace App\Services\Optical;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menentukan vendor perangkat dari sysObjectID (1.3.6.1.2.1.1.2.0) + sysDescr (1.3.6.1.2.1.1.1.0).
 *
 * Hasil disimpan di optical_device_vendors dan hanya dibaca ulang dari perangkat bila sudah
 * lebih dari 7 hari (atau 1 jam bila deteksi terakhir gagal), jadi polling tiap menit tidak
 * menambah permintaan SNMP.
 */
final class VendorDetector
{
    private ?bool $ready = null;

    public const TABLE = 'optical_device_vendors';

    /** Nomor enterprise IANA → vendor. Hanya mikrotik & huawei punya driver bawaan. */
    public const ENTERPRISES = [
        14988 => 'mikrotik',
        2011 => 'huawei',
        9 => 'cisco',
        2636 => 'juniper',
        25506 => 'h3c',
        30065 => 'arista',
        11 => 'hpe',
        3902 => 'zte',
        4881 => 'ruijie',
        171 => 'dlink',
        11863 => 'tplink',
        6027 => 'dell',
        1916 => 'extreme',
        8072 => 'net-snmp',
    ];

    private const FRESH_SECONDS = 7 * 86400;
    private const RETRY_SECONDS = 3600;

    /**
     * @return array{vendor:string, sys_object_id:?string, sys_descr:?string, override:?string}
     */
    public function detect(object $device, SnmpSession $snmp, bool $force = false): array
    {
        $cached = $this->cached((int) $device->id);
        if (!$force && $cached && $this->isFresh($cached)) {
            return $this->shape($cached);
        }

        $oidRaw = $snmp->get('1.3.6.1.2.1.1.2.0');
        $descrRaw = $snmp->get('1.3.6.1.2.1.1.1.0');
        $sysObjectId = $oidRaw !== false && preg_match('/(\d+(?:\.\d+)+)/', $oidRaw, $m) ? $m[1] : null;
        $sysDescr = $descrRaw !== false ? mb_substr(trim(preg_replace('/^STRING:\s*/', '', $descrRaw), " \""), 0, 1000) : null;

        $vendor = self::classify($sysObjectId, $sysDescr);
        $this->store((int) $device->id, $sysObjectId, $sysDescr, $vendor, $cached->driver_override ?? null);

        return ['vendor' => $vendor, 'sys_object_id' => $sysObjectId, 'sys_descr' => $sysDescr,
            'override' => $cached->driver_override ?? null];
    }

    public static function classify(?string $sysObjectId, ?string $sysDescr): string
    {
        if ($sysObjectId && preg_match('/^\.?1\.3\.6\.1\.4\.1\.(\d+)/', $sysObjectId, $m)) {
            $vendor = self::ENTERPRISES[(int) $m[1]] ?? 'generic';
            if ($vendor !== 'generic' && $vendor !== 'net-snmp') {
                return $vendor;
            }
        }
        $d = (string) $sysDescr;
        if (preg_match('/\b(VRP|Huawei|Quidway|CloudEngine)\b/i', $d)) {
            return 'huawei';
        }
        if (preg_match('/\b(RouterOS|MikroTik)\b/i', $d)) {
            return 'mikrotik';
        }

        return ($sysObjectId || $sysDescr) ? 'generic' : 'unknown';
    }

    public function cached(int $deviceId): ?object
    {
        if (!$this->tableReady()) {
            return null;
        }

        return DB::table(self::TABLE)->where('device_id', $deviceId)->first();
    }

    public function setOverride(int $deviceId, ?string $driver): void
    {
        DB::table(self::TABLE)->updateOrInsert(
            ['device_id' => $deviceId],
            ['driver_override' => $driver, 'updated_at' => now()] + (DB::table(self::TABLE)->where('device_id', $deviceId)->exists()
                ? [] : ['vendor' => 'unknown', 'created_at' => now()])
        );
    }

    private function isFresh(object $row): bool
    {
        if (!$row->detected_at) {
            return false;
        }
        $age = time() - strtotime((string) $row->detected_at);

        return $age < ($row->vendor === 'unknown' ? self::RETRY_SECONDS : self::FRESH_SECONDS);
    }

    private function store(int $deviceId, ?string $oid, ?string $descr, string $vendor, ?string $override): void
    {
        if (!$this->tableReady()) {
            return;
        }
        $exists = DB::table(self::TABLE)->where('device_id', $deviceId)->exists();
        DB::table(self::TABLE)->updateOrInsert(['device_id' => $deviceId], [
            'sys_object_id' => $oid,
            'sys_descr' => $descr,
            'vendor' => $vendor,
            'driver_override' => $override,
            'detected_at' => now(),
            'updated_at' => now(),
        ] + ($exists ? [] : ['created_at' => now()]));
    }

    private function shape(object $row): array
    {
        return ['vendor' => (string) $row->vendor, 'sys_object_id' => $row->sys_object_id,
            'sys_descr' => $row->sys_descr, 'override' => $row->driver_override];
    }

    private function tableReady(): bool
    {
        return $this->ready ??= Schema::hasTable(self::TABLE);
    }
}
