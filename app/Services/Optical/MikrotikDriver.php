<?php

namespace App\Services\Optical;

/**
 * MikroTik RouterOS — MIKROTIK-MIB mtxrOpticalTable (1.3.6.1.4.1.14988.1.1.19.1.1).
 * Terverifikasi di perangkat BMKV. Logika dipindahkan apa adanya dari InterfaceDiscovery:
 * nama port dari kolom .2, TX .9 dan RX .10 per ifIndex, satuan 0,001 dBm.
 */
final class MikrotikDriver implements OpticalDriver
{
    public function key(): string
    {
        return 'mikrotik';
    }

    public function read(SnmpSession $snmp, array $ifNameMap): array
    {
        $opticalMap = [];
        foreach ($snmp->walk('1.3.6.1.4.1.14988.1.1.19.1.1.2') as $val) {
            $optIfName = trim(str_replace(['STRING:', '"'], '', $val));
            if (!isset($ifNameMap[$optIfName])) {
                continue;
            }

            $ifIdx = $ifNameMap[$optIfName];
            $txRaw = $snmp->get("1.3.6.1.4.1.14988.1.1.19.1.1.9.$ifIdx");
            $rxRaw = $snmp->get("1.3.6.1.4.1.14988.1.1.19.1.1.10.$ifIdx");

            if ($txRaw !== false && $rxRaw !== false
                && preg_match('/-?\d+/', $txRaw, $m1) && preg_match('/-?\d+/', $rxRaw, $m2)) {
                $opticalMap[$optIfName] = [
                    'rx' => $m2[0] / 1000,
                    'tx' => $m1[0] / 1000,
                ];
            }
        }

        return $opticalMap;
    }
}
