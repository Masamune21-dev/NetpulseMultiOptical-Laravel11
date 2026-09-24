<?php

namespace App\Services\Optical;

/**
 * Sesi SNMP v2c tipis untuk satu perangkat.
 *
 * Host dan community SELALU berasal dari baris `snmp_devices` — tidak ada jalur di mana
 * pengguna web memasok alamat tujuan (mencegah SSRF lewat fitur uji profil OID).
 *
 * Timeout/retry sengaja sama dengan jalur polling lama: walk & get memakai 2 dtk × 2 retry,
 * sedangkan real walk memakai bawaan ekstensi (seperti pembacaan Huawei sebelumnya), supaya
 * perpindahan ke lapisan driver tidak mengubah perilaku di jaringan yang lossy.
 */
class SnmpSession
{
    public const TIMEOUT_US = 2000000;
    public const RETRIES = 2;

    public function __construct(private string $ip, private string $community)
    {
    }

    public function ip(): string
    {
        return $this->ip;
    }

    public function community(): string
    {
        return $this->community;
    }

    /** @return list<string> nilai walk (tanpa OID), kosong bila gagal */
    public function walk(string $oid): array
    {
        $r = @\snmp2_walk($this->ip, $this->community, $oid, self::TIMEOUT_US, self::RETRIES);

        return is_array($r) ? array_values($r) : [];
    }

    /** @return array<string,string> [oid => nilai], kosong bila gagal */
    public function realWalk(string $oid): array
    {
        $r = @\snmp2_real_walk($this->ip, $this->community, $oid);

        return is_array($r) ? $r : [];
    }

    public function get(string $oid): string|false
    {
        return @\snmp2_get($this->ip, $this->community, $oid, self::TIMEOUT_US, self::RETRIES);
    }

    /**
     * [ifName => ifIndex] dari IF-MIB, persis seperti InterfaceDiscovery membangunnya.
     * null bila perangkat tidak menjawab.
     *
     * @return array<string,int>|null
     */
    public function ifNameMap(): ?array
    {
        $ifIndex = $this->walk('1.3.6.1.2.1.2.2.1.1');
        $ifName = $this->walk('1.3.6.1.2.1.31.1.1.1.1');
        if ($ifIndex === [] || $ifName === []) {
            return null;
        }

        return self::buildIfNameMap($ifIndex, $ifName);
    }

    /** @return array<string,int> */
    public static function buildIfNameMap(array $ifIndex, array $ifName): array
    {
        $ifIndex = array_values($ifIndex);
        $ifName = array_values($ifName);
        $map = [];
        foreach ($ifIndex as $i => $raw) {
            $idx = (int) filter_var($raw, FILTER_SANITIZE_NUMBER_INT);
            $name = trim(str_replace(['STRING:', '"'], '', $ifName[$i] ?? ''));
            if ($name !== '') {
                $map[$name] = $idx;
            }
        }

        return $map;
    }
}
