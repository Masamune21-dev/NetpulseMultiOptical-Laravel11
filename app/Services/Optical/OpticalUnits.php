<?php

namespace App\Services\Optical;

/**
 * Konversi nilai daya optik mentah dari SNMP ke dBm.
 *
 * Satuan yang didukung sengaja terbatas pada yang benar-benar dipakai MIB vendor:
 *   dbm        nilai sudah dBm
 *   dbm_0_1    per sepuluh dBm      (mis. -215 → -21.5)
 *   dbm_0_01   per seratus dBm      (JUNIPER-DOM-MIB, HH3C-TRANSCEIVER-INFO-MIB)
 *   dbm_0_001  per seribu dBm       (MikroTik mtxrOptical*Power)
 *   mw         miliwatt             → 10·log10(mW)
 *   uw_0_1     0,1 mikrowatt        → 10·log10(nilai × 0,0001 mW)
 */
final class OpticalUnits
{
    public const UNITS = [
        'dbm' => 'dBm',
        'dbm_0_1' => '0,1 dBm',
        'dbm_0_01' => '0,01 dBm',
        'dbm_0_001' => '0,001 dBm',
        'mw' => 'mW',
        'uw_0_1' => '0,1 µW',
    ];

    /** Rentang wajar modul optik; di luar ini dianggap "tidak ada pembacaan". */
    public const MIN_DBM = -60.0;
    public const MAX_DBM = 10.0;

    public static function isValidUnit(string $unit): bool
    {
        return array_key_exists($unit, self::UNITS);
    }

    public static function toDbm(float $raw, string $unit): ?float
    {
        $dbm = match ($unit) {
            'dbm' => $raw,
            'dbm_0_1' => $raw / 10,
            'dbm_0_01' => $raw / 100,
            'dbm_0_001' => $raw / 1000,
            'mw' => $raw > 0 ? 10 * log10($raw) : null,
            'uw_0_1' => $raw > 0 ? 10 * log10($raw * 0.0001) : null,
            default => null,
        };

        return self::sane($dbm);
    }

    /**
     * Nilai ENTITY-SENSOR-MIB (RFC 3433) / CISCO-ENTITY-SENSOR-MIB bertipe watts(6) → dBm.
     *
     * nilai sebenarnya = value × 10^(3·(scale−9)) / 10^precision  [watt]
     * scale mengikuti SensorDataScale: yocto(1) … milli(8), units(9), kilo(10) … tera(13).
     * Skala di atas 13 tidak dipakai modul optik dan enumerasinya di MIB tidak lagi
     * berkelipatan 10³, jadi ditolak.
     */
    public static function sensorWattsToDbm(int $value, int $scale, int $precision): ?float
    {
        if ($scale < 1 || $scale > 13 || $precision < -8 || $precision > 9) {
            return null;
        }
        $watts = $value * (10 ** (3 * ($scale - 9))) / (10 ** $precision);
        if ($watts <= 0) {
            return null;
        }

        return self::sane(10 * log10($watts * 1000));
    }

    public static function sane(?float $dbm): ?float
    {
        if ($dbm === null || is_nan($dbm) || $dbm < self::MIN_DBM || $dbm > self::MAX_DBM) {
            return null;
        }

        return round($dbm, 3);
    }

    /** Ambil angka pertama (boleh negatif/desimal) dari nilai SNMP seperti "INTEGER: -2150". */
    public static function parseNumber(string $raw): ?float
    {
        return preg_match('/-?\d+(?:\.\d+)?/', $raw, $m) ? (float) $m[0] : null;
    }
}
