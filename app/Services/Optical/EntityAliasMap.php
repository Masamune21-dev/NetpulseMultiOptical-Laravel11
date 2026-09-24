<?php

namespace App\Services\Optical;

/**
 * ENTITY-MIB (RFC 6933) entAliasMappingIdentifier 1.3.6.1.2.1.47.1.3.2.1.2:
 * [entPhysicalIndex => ifIndex]. Indeks baris: <entPhysicalIndex>.<logicalIndex>,
 * nilainya penunjuk OID yang berakhir di ifIndex, mis. ".1.3.6.1.2.1.2.2.1.1.67".
 */
final class EntityAliasMap
{
    /** @return array<int,int> */
    public static function read(SnmpSession $snmp): array
    {
        return self::parse($snmp->realWalk('1.3.6.1.2.1.47.1.3.2.1.2'));
    }

    /** @return array<int,int> */
    public static function parse(array $walk): array
    {
        $map = [];
        foreach ($walk as $oid => $val) {
            if (preg_match('/\.(\d+)\.(\d+)$/', (string) $oid, $om) && preg_match('/\.(\d+)$/', (string) $val, $vm)) {
                $map[(int) $om[1]] = (int) $vm[1];
            }
        }

        return $map;
    }
}
