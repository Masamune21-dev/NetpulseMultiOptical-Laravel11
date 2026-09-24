<?php

namespace App\Services\Optical;

/**
 * Pembaca generik berbasis standar: ENTITY-SENSOR-MIB (RFC 3433, 1.3.6.1.2.1.99.1.1.1)
 * dan padanan Cisco CISCO-ENTITY-SENSOR-MIB (1.3.6.1.4.1.9.9.91.1.1.1.1). Kolom kedua tabel
 * sama urutannya: 1 type, 2 scale, 3 precision, 4 value, 5 status/operStatus.
 *
 * BERBASIS STANDAR, BELUM DIVERIFIKASI DI PERANGKAT BMKV (semua perangkat BMKV saat ini
 * MikroTik/Huawei). Keterbatasan yang disengaja:
 *   - hanya sensor bertipe watts(6). Enumerasi SensorDataType di RFC 3433 maupun
 *     CISCO-ENTITY-SENSOR-MIB rev. 2020-09-22 tidak memuat tipe dBm, jadi tidak ditebak.
 *   - RX/TX ditentukan dari entPhysicalDescr/entPhysicalName sensor ("Rx"/"Receive" vs
 *     "Tx"/"Transmit"). Sensor yang namanya tidak jelas dilewati.
 *   - sensor → ifIndex lewat entAliasMappingIdentifier milik sensor atau entitas induknya
 *     (entPhysicalContainedIn, maks. 4 tingkat); bila tak ada, nama port di label sensor.
 */
final class EntitySensorDriver implements OpticalDriver
{
    private const STD_BASE = '1.3.6.1.2.1.99.1.1.1';
    private const CISCO_BASE = '1.3.6.1.4.1.9.9.91.1.1.1.1';
    private const ENT_DESCR = '1.3.6.1.2.1.47.1.1.1.1.2';
    private const ENT_CONTAINED_IN = '1.3.6.1.2.1.47.1.1.1.1.4';
    private const ENT_NAME = '1.3.6.1.2.1.47.1.1.1.1.7';
    private const TYPE_WATTS = 6;
    private const STATUS_OK = 1;

    public function key(): string
    {
        return 'entity_sensor';
    }

    public function read(SnmpSession $snmp, array $ifNameMap): array
    {
        $base = self::STD_BASE;
        $types = self::column($snmp->realWalk($base . '.1'));
        if (!in_array(self::TYPE_WATTS, $types, true)) {
            $base = self::CISCO_BASE;
            $types = self::column($snmp->realWalk($base . '.1'));
            if (!in_array(self::TYPE_WATTS, $types, true)) {
                return [];
            }
        }

        return self::build(
            $types,
            self::column($snmp->realWalk($base . '.2')),
            self::column($snmp->realWalk($base . '.3')),
            self::column($snmp->realWalk($base . '.4')),
            self::column($snmp->realWalk($base . '.5')),
            self::strings($snmp->realWalk(self::ENT_DESCR)),
            self::strings($snmp->realWalk(self::ENT_NAME)),
            self::column($snmp->realWalk(self::ENT_CONTAINED_IN)),
            EntityAliasMap::read($snmp),
            $ifNameMap,
        );
    }

    /**
     * Inti murni (tanpa jaringan) supaya bisa diuji dengan fixture.
     *
     * @param  array<int,int>  $types  [entPhysicalIndex => type]
     * @return array<string,array{rx:?float,tx:?float}>
     */
    public static function build(
        array $types, array $scales, array $precisions, array $values, array $statuses,
        array $descrs, array $names, array $containedIn, array $alias, array $ifNameMap,
    ): array {
        $ifIdxToName = array_flip($ifNameMap);
        $result = [];

        foreach ($types as $phys => $type) {
            if ($type !== self::TYPE_WATTS || !isset($values[$phys])) {
                continue;
            }
            $label = trim(($names[$phys] ?? '') . ' ' . ($descrs[$phys] ?? ''));
            $direction = self::direction($label);
            if ($direction === null) {
                continue;
            }
            $ifName = self::interfaceFor($phys, $containedIn, $alias, $ifIdxToName, $label, $ifNameMap);
            if ($ifName === null) {
                continue;
            }

            $dbm = null;
            if (($statuses[$phys] ?? self::STATUS_OK) === self::STATUS_OK) {
                $dbm = OpticalUnits::sensorWattsToDbm($values[$phys], $scales[$phys] ?? 9, $precisions[$phys] ?? 0);
            }

            $result[$ifName] ??= ['rx' => null, 'tx' => null];
            // Modul multi-lane punya beberapa sensor per port; lane pertama yang terbaca dipakai.
            $result[$ifName][$direction] ??= $dbm;
        }

        return $result;
    }

    public static function direction(string $label): ?string
    {
        if (preg_match('/bias|current|voltage|temp/i', $label)) {
            return null;
        }
        $rx = (bool) preg_match('/\b(rx|receive[ds]?|input)\b/i', $label);
        $tx = (bool) preg_match('/\b(tx|transmit(ted)?|output)\b/i', $label);

        return $rx === $tx ? null : ($rx ? 'rx' : 'tx');
    }

    private static function interfaceFor(
        int $phys, array $containedIn, array $alias, array $ifIdxToName, string $label, array $ifNameMap,
    ): ?string {
        $node = $phys;
        for ($depth = 0; $depth <= 4 && $node > 0; $depth++) {
            if (isset($alias[$node], $ifIdxToName[$alias[$node]])) {
                return $ifIdxToName[$alias[$node]];
            }
            $node = $containedIn[$node] ?? 0;
        }

        // Cadangan: nama port utuh di label sensor, pilih yang terpanjang supaya
        // "Ethernet1/10" tidak tertukar dengan "Ethernet1/1".
        $best = null;
        foreach (array_keys($ifNameMap) as $ifName) {
            $pattern = '/(^|[\s:,(])' . preg_quote((string) $ifName, '/') . '($|[\s:,)])/i';
            if (preg_match($pattern, $label) && ($best === null || strlen((string) $ifName) > strlen($best))) {
                $best = (string) $ifName;
            }
        }

        return $best;
    }

    /** @return array<int,int> [indeks terakhir => nilai integer] */
    private static function column(array $walk): array
    {
        $out = [];
        foreach ($walk as $oid => $val) {
            if (preg_match('/\.(\d+)$/', (string) $oid, $m) && preg_match('/-?\d+/', (string) $val, $vm)) {
                $out[(int) $m[1]] = (int) $vm[0];
            }
        }

        return $out;
    }

    /** @return array<int,string> */
    private static function strings(array $walk): array
    {
        $out = [];
        foreach ($walk as $oid => $val) {
            if (preg_match('/\.(\d+)$/', (string) $oid, $m)) {
                $out[(int) $m[1]] = trim(preg_replace('/^STRING:\s*/', '', (string) $val), " \"");
            }
        }

        return $out;
    }
}
