<?php

namespace App\Services\Optical;

/**
 * Profil OID buatan admin (tabel optical_profiles) untuk vendor yang belum punya driver.
 *
 * Profil hanya berisi OID dan aturan konversi — host & community selalu diambil dari
 * perangkat yang sudah terdaftar, jadi fitur ini tidak bisa dipakai menembak alamat lain.
 */
final class CustomProfileDriver implements OpticalDriver
{
    public const INDEX_TYPES = ['if_index' => 'ifIndex', 'ent_physical' => 'entPhysicalIndex (via ENTITY-MIB)'];

    public function __construct(private object $profile)
    {
    }

    public function key(): string
    {
        return 'profile:' . (int) $this->profile->id;
    }

    public function read(SnmpSession $snmp, array $ifNameMap): array
    {
        $rx = $snmp->realWalk((string) $this->profile->rx_oid);
        $tx = $this->profile->tx_oid ? $snmp->realWalk((string) $this->profile->tx_oid) : [];
        $alias = $this->profile->index_type === 'ent_physical' ? EntityAliasMap::read($snmp) : [];

        return self::build($this->profile, $rx, $tx, $alias, $ifNameMap)['optics'];
    }

    /**
     * Inti murni: juga dipakai tombol "Uji" untuk pratinjau (nilai mentah + hasil dBm).
     *
     * @return array{optics: array<string,array{rx:?float,tx:?float}>, rows: list<array>}
     */
    public static function build(object $profile, array $rxWalk, array $txWalk, array $alias, array $ifNameMap): array
    {
        $ifIdxToName = array_flip($ifNameMap);
        $invalid = self::invalidValues($profile->invalid_values ?? null);
        $unit = (string) $profile->value_unit;
        $optics = [];
        $rows = [];

        foreach (['rx' => [$rxWalk, (string) $profile->rx_oid], 'tx' => [$txWalk, (string) ($profile->tx_oid ?? '')]] as $dir => [$walk, $base]) {
            foreach ($walk as $oid => $val) {
                $index = self::rowIndex((string) $oid, $base);
                if ($index === null) {
                    continue;
                }
                $ifIndex = $profile->index_type === 'ent_physical' ? ($alias[$index] ?? null) : $index;
                $ifName = $ifIndex !== null ? ($ifIdxToName[$ifIndex] ?? null) : null;
                $raw = OpticalUnits::parseNumber((string) $val);
                $dbm = ($raw === null || in_array($raw, $invalid, true)) ? null : OpticalUnits::toDbm($raw, $unit);

                $rows[] = ['dir' => $dir, 'index' => $index, 'if_index' => $ifIndex, 'if_name' => $ifName, 'raw' => $raw, 'dbm' => $dbm];
                if ($ifName === null) {
                    continue;
                }
                $optics[$ifName] ??= ['rx' => null, 'tx' => null];
                $optics[$ifName][$dir] = $dbm;
            }
        }

        return ['optics' => $optics, 'rows' => $rows];
    }

    /** Indeks baris = komponen terakhir sesudah OID dasar. */
    private static function rowIndex(string $oid, string $base): ?int
    {
        $oid = ltrim(preg_replace('/^iso(?=\.)/', '1', $oid), '.');
        $base = ltrim($base, '.');
        if ($base !== '' && str_starts_with($oid, $base . '.')) {
            $oid = substr($oid, strlen($base) + 1);
        }

        return preg_match('/(\d+)$/', $oid, $m) ? (int) $m[1] : null;
    }

    /** @return list<float> */
    public static function invalidValues(mixed $stored): array
    {
        $list = is_array($stored) ? $stored : (json_decode((string) $stored, true) ?: []);

        return array_values(array_map('floatval', array_filter($list, 'is_numeric')));
    }

    /**
     * OID numerik bertitik saja, 9–40 komponen. Minimal 9 komponen (kedalaman satu kolom tabel,
     * mis. 1.3.6.1.4.1.x.y.z) supaya "Uji" tidak bisa dipakai untuk walk seluruh subtree besar
     * seperti 1.3.6.1.2.1 yang membebani perangkat.
     */
    public static function isValidOid(?string $oid): bool
    {
        return $oid !== null && (bool) preg_match('/^\.?1(\.\d{1,10}){8,39}$/', $oid);
    }

    /** Awalan sysObjectID untuk pencocokan: numerik, minimal 1.3.6.1.4.1.<enterprise> (7 komponen). */
    public static function isValidOidPrefix(?string $oid): bool
    {
        return $oid !== null && (bool) preg_match('/^\.?1(\.\d{1,10}){6,39}$/', $oid);
    }
}
