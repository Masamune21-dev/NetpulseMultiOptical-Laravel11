<?php

namespace App\Services\Optical;

/**
 * Huawei VRP (S-series, CloudEngine, Quidway) — HUAWEI-ENTITY-EXTENT-MIB
 * hwEntityOpticalRxPower (.8) / hwEntityOpticalTxPower (.9), dipetakan ke ifIndex lewat
 * ENTITY-MIB entAliasMappingIdentifier. Terverifikasi di perangkat BMKV; logika dipindahkan
 * apa adanya dari InterfaceDiscovery::buildHuaweiOpticalMap.
 */
final class HuaweiDriver implements OpticalDriver
{
    public function key(): string
    {
        return 'huawei';
    }

    public function read(SnmpSession $snmp, array $ifNameMap): array
    {
        $ifIdxToName = [];
        foreach ($ifNameMap as $name => $idx) {
            $ifIdxToName[$idx] = $name;
        }

        $physToIfIdx = EntityAliasMap::read($snmp);

        $rxWalk = $snmp->realWalk('1.3.6.1.4.1.2011.5.25.31.1.1.3.1.8');
        $txWalk = $snmp->realWalk('1.3.6.1.4.1.2011.5.25.31.1.1.3.1.9');
        if ($rxWalk === [] && $txWalk === []) {
            return [];
        }

        $rxByPhys = self::byLastIndex($rxWalk);
        $txByPhys = self::byLastIndex($txWalk);

        $result = [];
        foreach (array_unique(array_merge(array_keys($rxByPhys), array_keys($txByPhys))) as $physIdx) {
            $ifIdx = $physToIfIdx[$physIdx] ?? null;
            $ifName = $ifIdx !== null ? ($ifIdxToName[$ifIdx] ?? null) : null;
            if ($ifName === null) {
                continue;
            }

            $result[$ifName] = [
                'rx' => isset($rxByPhys[$physIdx]) ? self::normalize($rxByPhys[$physIdx]) : null,
                'tx' => isset($txByPhys[$physIdx]) ? self::normalize($txByPhys[$physIdx]) : null,
            ];
        }

        return $result;
    }

    /** @return array<int,int> */
    private static function byLastIndex(array $walk): array
    {
        $out = [];
        foreach ($walk as $oid => $val) {
            if (preg_match('/\.(\d+)$/', $oid, $m) && preg_match('/(-?\d+)/', $val, $vm)) {
                $out[(int) $m[1]] = (int) $vm[1];
            }
        }

        return $out;
    }

    /**
     * Huawei melaporkan dua satuan tergantung platform/firmware:
     * 0,01 dBm bila nilai ≤ 0, mikrowatt bila nilai > 0.
     */
    public static function normalize(int $raw): ?float
    {
        $dbm = $raw <= 0 ? $raw / 100.0 : 10.0 * log10($raw / 1000.0);
        if ($dbm < -60.0 || $dbm > 10.0) {
            return null;
        }

        return round($dbm, 3);
    }
}
