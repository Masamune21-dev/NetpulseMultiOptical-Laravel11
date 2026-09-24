<?php

namespace Tests\Unit;

use App\Services\Optical\CustomProfileDriver;
use App\Services\Optical\EntityAliasMap;
use App\Services\Optical\EntitySensorDriver;
use App\Services\Optical\HuaweiDriver;
use App\Services\Optical\MikrotikDriver;
use App\Services\Optical\OpticalDriverResolver;
use App\Services\Optical\OpticalUnits;
use App\Services\Optical\SnmpSession;
use App\Services\Optical\VendorDetector;
use PHPUnit\Framework\TestCase;

/**
 * Parsing driver optik dengan fixture berbentuk keluaran SNMP asli (nilai rekaan,
 * alamat dokumentasi RFC 5737). Tidak ada jaringan: SnmpSession diganti FakeSnmp.
 */
class OpticalDriversTest extends TestCase
{
    public function test_unit_conversions(): void
    {
        $this->assertSame(-21.5, OpticalUnits::toDbm(-2150, 'dbm_0_01'));
        $this->assertSame(-3.2, OpticalUnits::toDbm(-32, 'dbm_0_1'));
        $this->assertSame(-5.123, OpticalUnits::toDbm(-5123, 'dbm_0_001'));
        $this->assertSame(-7.0, OpticalUnits::toDbm(-7, 'dbm'));
        $this->assertSame(0.0, OpticalUnits::toDbm(1.0, 'mw'));            // 1 mW = 0 dBm
        $this->assertSame(-10.0, OpticalUnits::toDbm(1000, 'uw_0_1'));     // 100 µW = 0,1 mW
        $this->assertNull(OpticalUnits::toDbm(0, 'mw'));                   // tanpa cahaya
        $this->assertSame(-40.0, OpticalUnits::toDbm(-4000, 'dbm_0_01'));  // -40 dBm masih sah
        $this->assertNull(OpticalUnits::toDbm(-9000, 'dbm_0_01'));         // -90 dBm di luar rentang
        $this->assertNull(OpticalUnits::toDbm(5, 'bukan-satuan'));
    }

    public function test_sensor_watts_scale_and_precision(): void
    {
        // 0,5 mW sebagai milli(8) presisi 1 → value 5 → 0,0005 W → -3,01 dBm
        $this->assertSame(-3.01, OpticalUnits::sensorWattsToDbm(5, 8, 1));
        // micro(7) presisi 0: 250 µW → -6,021 dBm
        $this->assertSame(-6.021, OpticalUnits::sensorWattsToDbm(250, 7, 0));
        $this->assertNull(OpticalUnits::sensorWattsToDbm(0, 8, 1));
        $this->assertNull(OpticalUnits::sensorWattsToDbm(5, 14, 0));      // skala di luar tabel
    }

    public function test_vendor_classification(): void
    {
        $this->assertSame('mikrotik', VendorDetector::classify('1.3.6.1.4.1.14988.1', null));
        $this->assertSame('huawei', VendorDetector::classify('.1.3.6.1.4.1.2011.2.23.100', null));
        $this->assertSame('juniper', VendorDetector::classify('1.3.6.1.4.1.2636.1.1.1.2.1', null));
        $this->assertSame('huawei', VendorDetector::classify('1.3.6.1.4.1.8072.3.2.10', 'Huawei Versatile Routing Platform (VRP)'));
        $this->assertSame('mikrotik', VendorDetector::classify(null, 'RouterOS CCR2004'));
        $this->assertSame('generic', VendorDetector::classify('1.3.6.1.4.1.99999.1', 'ACME OS'));
        $this->assertSame('unknown', VendorDetector::classify(null, null));
    }

    public function test_mikrotik_driver_matches_legacy_shape(): void
    {
        $snmp = new FakeSnmp(
            walks: ['1.3.6.1.4.1.14988.1.1.19.1.1.2' => ['STRING: "sfp-sfpplus1"', 'STRING: "sfp2"']],
            gets: [
                '1.3.6.1.4.1.14988.1.1.19.1.1.9.1' => 'INTEGER: -2345',
                '1.3.6.1.4.1.14988.1.1.19.1.1.10.1' => 'INTEGER: -18765',
                '1.3.6.1.4.1.14988.1.1.19.1.1.9.2' => false,
            ],
        );
        $map = (new MikrotikDriver())->read($snmp, ['sfp-sfpplus1' => 1, 'sfp2' => 2]);

        $this->assertSame(['sfp-sfpplus1' => ['rx' => -18.765, 'tx' => -2.345]], $map);
    }

    public function test_huawei_driver_uses_alias_map_and_dual_units(): void
    {
        $snmp = new FakeSnmp(realWalks: [
            '1.3.6.1.2.1.47.1.3.2.1.2' => [
                'iso.3.6.1.2.1.47.1.3.2.1.2.67108873.0' => 'OID: .1.3.6.1.2.1.2.2.1.1.10',
                'iso.3.6.1.2.1.47.1.3.2.1.2.67108874.0' => 'OID: .1.3.6.1.2.1.2.2.1.1.11',
            ],
            '1.3.6.1.4.1.2011.5.25.31.1.1.3.1.8' => [
                'iso.3.6.1.4.1.2011.5.25.31.1.1.3.1.8.67108873' => 'INTEGER: -1834', // 0,01 dBm
                'iso.3.6.1.4.1.2011.5.25.31.1.1.3.1.8.67108874' => 'INTEGER: 500',   // µW
            ],
            '1.3.6.1.4.1.2011.5.25.31.1.1.3.1.9' => [
                'iso.3.6.1.4.1.2011.5.25.31.1.1.3.1.9.67108873' => 'INTEGER: -245',
            ],
        ]);
        $map = (new HuaweiDriver())->read($snmp, ['XGigabitEthernet0/0/1' => 10, 'XGigabitEthernet0/0/2' => 11]);

        $this->assertSame(-18.34, $map['XGigabitEthernet0/0/1']['rx']);
        $this->assertSame(-2.45, $map['XGigabitEthernet0/0/1']['tx']);
        $this->assertSame(-3.01, $map['XGigabitEthernet0/0/2']['rx']);
        $this->assertNull($map['XGigabitEthernet0/0/2']['tx']);
    }

    public function test_entity_sensor_driver_maps_via_container_and_name(): void
    {
        $map = EntitySensorDriver::build(
            types: [101 => 6, 102 => 6, 103 => 5, 201 => 6],
            scales: [101 => 8, 102 => 8, 103 => 8, 201 => 7],
            precisions: [101 => 1, 102 => 1, 103 => 1, 201 => 0],
            values: [101 => 5, 102 => 7, 103 => 60, 201 => 250],
            statuses: [101 => 1, 102 => 1, 103 => 1, 201 => 1],
            descrs: [101 => 'Receive Power Sensor', 102 => 'Transmit Power Sensor', 103 => 'Bias Current Sensor', 201 => 'Ethernet1/10 Rx Power'],
            names: [101 => 'Te1/1 Receive Power', 102 => 'Te1/1 Transmit Power'],
            containedIn: [101 => 100, 102 => 100, 103 => 100],
            alias: [100 => 5],
            ifNameMap: ['Te1/1' => 5, 'Ethernet1/1' => 6, 'Ethernet1/10' => 7],
        );

        $this->assertSame(['rx' => -3.01, 'tx' => -1.549], $map['Te1/1']);
        // Tanpa alias: cocok lewat nama port terpanjang, bukan Ethernet1/1.
        $this->assertSame(['rx' => -6.021, 'tx' => null], $map['Ethernet1/10']);
        $this->assertArrayNotHasKey('Ethernet1/1', $map);
    }

    public function test_entity_sensor_direction_labels(): void
    {
        $this->assertSame('rx', EntitySensorDriver::direction('DOM RX Power for Ethernet49/1'));
        $this->assertSame('tx', EntitySensorDriver::direction('GigabitEthernet1/0/1 Transmit Power Sensor'));
        $this->assertNull(EntitySensorDriver::direction('Tx Bias Current'));
        $this->assertNull(EntitySensorDriver::direction('Module Temperature'));
    }

    public function test_custom_profile_build_ifindex_and_invalid_values(): void
    {
        $profile = (object) [
            'id' => 1, 'rx_oid' => '1.3.6.1.4.1.2636.3.60.1.1.1.1.5', 'tx_oid' => '1.3.6.1.4.1.2636.3.60.1.1.1.1.7',
            'index_type' => 'if_index', 'value_unit' => 'dbm_0_01', 'invalid_values' => json_encode([-4000]),
        ];
        $out = CustomProfileDriver::build($profile,
            ['iso.3.6.1.4.1.2636.3.60.1.1.1.1.5.513' => 'INTEGER: -1502', 'iso.3.6.1.4.1.2636.3.60.1.1.1.1.5.514' => 'INTEGER: -4000'],
            ['iso.3.6.1.4.1.2636.3.60.1.1.1.1.7.513' => 'INTEGER: -210'],
            [],
            ['xe-0/0/0' => 513, 'xe-0/0/1' => 514],
        );

        $this->assertSame(['rx' => -15.02, 'tx' => -2.1], $out['optics']['xe-0/0/0']);
        $this->assertSame(['rx' => null, 'tx' => null], $out['optics']['xe-0/0/1']);
        $this->assertCount(3, $out['rows']);
    }

    public function test_custom_profile_ent_physical_index(): void
    {
        $profile = (object) ['id' => 2, 'rx_oid' => '1.3.6.1.4.1.99999.1.2.3.1.5', 'tx_oid' => null,
            'index_type' => 'ent_physical', 'value_unit' => 'dbm_0_1', 'invalid_values' => null];
        $out = CustomProfileDriver::build($profile,
            ['.1.3.6.1.4.1.99999.1.2.3.1.5.9001' => 'INTEGER: -123'], [],
            EntityAliasMap::parse(['.1.3.6.1.2.1.47.1.3.2.1.2.9001.0' => 'OID: .1.3.6.1.2.1.2.2.1.1.3']),
            ['port3' => 3],
        );

        $this->assertSame(-12.3, $out['optics']['port3']['rx']);
    }

    public function test_oid_validation_blocks_symbolic_and_shallow_oids(): void
    {
        $this->assertTrue(CustomProfileDriver::isValidOid('1.3.6.1.4.1.2636.3.60.1.1.1.1.5'));
        $this->assertTrue(CustomProfileDriver::isValidOid('.1.3.6.1.4.1.25506.2.70.1.1.1.12'));
        $this->assertFalse(CustomProfileDriver::isValidOid('1.3.6.1.2.1'));              // subtree besar
        $this->assertFalse(CustomProfileDriver::isValidOid('ifDescr'));
        $this->assertFalse(CustomProfileDriver::isValidOid('1.3.6.1.4.1.9.9;id'));
        $this->assertFalse(CustomProfileDriver::isValidOid('192.0.2.10'));
        $this->assertTrue(CustomProfileDriver::isValidOidPrefix('1.3.6.1.4.1.2636'));
        $this->assertFalse(CustomProfileDriver::isValidOidPrefix('1.3.6.1'));
    }

    public function test_profile_matching_rules(): void
    {
        $p = (object) ['match_sys_object_id' => '1.3.6.1.4.1.2636', 'match_sys_descr' => null];
        $this->assertTrue(OpticalDriverResolver::profileMatches($p, ['sys_object_id' => '1.3.6.1.4.1.2636.1.1.1.2.1']));
        $this->assertFalse(OpticalDriverResolver::profileMatches($p, ['sys_object_id' => '1.3.6.1.4.1.26360.1']));

        $q = (object) ['match_sys_object_id' => null, 'match_sys_descr' => 'ACME OS [0-9]+'];
        $this->assertTrue(OpticalDriverResolver::profileMatches($q, ['sys_descr' => 'acme os 7 build']));

        $none = (object) ['match_sys_object_id' => null, 'match_sys_descr' => null];
        $this->assertFalse(OpticalDriverResolver::profileMatches($none, ['sys_object_id' => '1.3.6.1.4.1.2636.1']));
    }
}

/** SnmpSession palsu: tidak pernah menyentuh jaringan. */
class FakeSnmp extends SnmpSession
{
    public function __construct(private array $walks = [], private array $gets = [], private array $realWalks = [])
    {
        parent::__construct('192.0.2.1', 'rahasia-uji');
    }

    public function walk(string $oid): array
    {
        return $this->walks[$oid] ?? [];
    }

    public function realWalk(string $oid): array
    {
        return $this->realWalks[$oid] ?? [];
    }

    public function get(string $oid): string|false
    {
        return $this->gets[$oid] ?? false;
    }
}
