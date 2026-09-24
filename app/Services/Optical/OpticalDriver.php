<?php

namespace App\Services\Optical;

/**
 * Satu cara membaca daya optik (DDM) dari sebuah perangkat.
 *
 * read() mengembalikan [ifName => ['rx' => ?float, 'tx' => ?float]] dalam dBm — bentuk yang
 * sama dengan peta optik yang dulu dibangun langsung di InterfaceDiscovery, supaya kode
 * penyimpanan statistik & alert tidak perlu berubah.
 */
interface OpticalDriver
{
    /** Kunci stabil, dipakai di override perangkat dan laporan (mis. "mikrotik", "profile:3"). */
    public function key(): string;

    /**
     * @param  array<string,int>  $ifNameMap  [ifName => ifIndex]
     * @return array<string,array{rx:?float,tx:?float}>
     */
    public function read(SnmpSession $snmp, array $ifNameMap): array;
}
