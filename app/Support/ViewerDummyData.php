<?php

namespace App\Support;

use Illuminate\Http\Request;

final class ViewerDummyData
{
    public static function isViewer(?Request $request = null): bool
    {
        $role = $request
            ? (string) ($request->session()->get('auth.user.role') ?? '')
            : (string) (session('auth.user.role') ?? '');

        return $role === 'viewer';
    }

    public static function dashboardCounts(): array
    {
        return [
            'deviceCount' => 6,
            'ifCount' => 248,
            'sfpCount' => 32,
            'badOptical' => 3,
            'userCount' => 8,
            'oltCount' => 2,
            'ponCount' => 8,
            'onuCount' => 512,
        ];
    }

    public static function devices(): array
    {
        $now = date('Y-m-d H:i:s');
        return [
            [
                'id' => 101,
                'device_name' => 'RTR-CORE-DEMO',
                'ip_address' => '10.10.0.1',
                'snmp_version' => '2c',
                'community' => null,
                'snmp_user' => null,
                'is_active' => 1,
                'last_status' => 'OK',
                'last_error' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 102,
                'device_name' => 'SW-AGG-DEMO',
                'ip_address' => '10.10.0.2',
                'snmp_version' => '2c',
                'community' => null,
                'snmp_user' => null,
                'is_active' => 1,
                'last_status' => 'OK',
                'last_error' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 103,
                'device_name' => 'OLT-HIOSO-DEMO',
                'ip_address' => '10.10.10.1',
                'snmp_version' => '2c',
                'community' => null,
                'snmp_user' => null,
                'is_active' => 1,
                'last_status' => 'OK',
                'last_error' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 104,
                'device_name' => 'EDGE-POP-DEMO',
                'ip_address' => '10.10.0.4',
                'snmp_version' => '3',
                'community' => null,
                'snmp_user' => 'snmpv3-demo',
                'is_active' => 1,
                'last_status' => 'FAILED',
                'last_error' => 'Timeout (demo)',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];
    }

    public static function monitoringDevices(): array
    {
        return array_map(
            fn ($d) => ['id' => $d['id'], 'device_name' => $d['device_name']],
            self::devices()
        );
    }

    public static function interfaces(int $deviceId): array
    {
        $base = [
            [
                'id' => 9001,
                'if_index' => 1,
                'if_name' => 'sfp-sfpplus1',
                'if_alias' => 'Uplink-1',
                'optical_index' => 1,
                'rx_power' => -17.20,
                'tx_power' => -1.10,
                'last_seen' => date('Y-m-d H:i:s'),
                'is_sfp' => 1,
            ],
            [
                'id' => 9002,
                'if_index' => 2,
                'if_name' => 'sfp-sfpplus2',
                'if_alias' => 'Uplink-2',
                'optical_index' => 2,
                'rx_power' => -26.80,
                'tx_power' => -2.40,
                'last_seen' => date('Y-m-d H:i:s'),
                'is_sfp' => 1,
            ],
            [
                'id' => 9003,
                'if_index' => 3,
                'if_name' => 'ether3',
                'if_alias' => 'Access-1',
                'optical_index' => null,
                'rx_power' => null,
                'tx_power' => null,
                'last_seen' => date('Y-m-d H:i:s'),
                'is_sfp' => 0,
            ],
        ];

        // Make deviceId affect values slightly to avoid identical screens.
        $shift = ($deviceId % 7) / 10.0;
        foreach ($base as &$row) {
            if ($row['rx_power'] !== null) $row['rx_power'] = (float) $row['rx_power'] - $shift;
            if ($row['tx_power'] !== null) $row['tx_power'] = (float) $row['tx_power'] - ($shift / 2.0);
        }

        return $base;
    }

    public static function monitoringInterfaces(int $deviceId): array
    {
        $ifs = self::interfaces($deviceId);
        $out = [];
        foreach ($ifs as $i) {
            if ((int) ($i['is_sfp'] ?? 0) !== 1) continue;
            $out[] = [
                'if_index' => $i['if_index'],
                'if_name' => $i['if_name'],
                'if_alias' => $i['if_alias'] ?? null,
                'tx_power' => $i['tx_power'],
                'rx_power' => $i['rx_power'],
            ];
        }
        return $out;
    }

    public static function interfaceChart(int $deviceId, int $ifIndex, string $range): array
    {
        $points = match ($range) {
            '1h' => 12,
            '1d' => 24,
            '3d' => 36,
            '7d' => 56,
            '30d' => 60,
            '1y' => 60,
            default => 12,
        };

        $stepSec = match ($range) {
            '1h' => 300,
            '1d' => 3600,
            '3d' => 2 * 3600,
            '7d' => 3 * 3600,
            '30d' => 12 * 3600,
            '1y' => 6 * 24 * 3600,
            default => 300,
        };

        $seed = ($deviceId * 31) + ($ifIndex * 7);
        $txBase = -2.0 - (($seed % 9) / 10.0);
        $rxBase = -20.0 - (($seed % 17) / 10.0);

        $now = time();
        $out = [];
        for ($i = $points - 1; $i >= 0; $i--) {
            $t = $now - ($i * $stepSec);
            $n = (($seed + $t) % 13) / 10.0;
            $tx = $txBase + ($n / 3.0);
            $rx = $rxBase - ($n);
            $loss = $tx - $rx;

            $out[] = [
                'created_at' => date('Y-m-d H:i:s', $t),
                'tx_power' => round($tx, 3),
                'rx_power' => round($rx, 3),
                'loss' => round($loss, 3),
            ];
        }

        return $out;
    }

    public static function mapNodes(bool $withInterfaces): array
    {
        $now = date('Y-m-d H:i:s');
        // Keep nodes close to the default map view (map.js sets view around -6.7489, 110.9752).
        $centerLng = 110.975234;
        $centerLat = -6.748974;
        $nodes = [
            [
                'id' => 1,
                'device_id' => 101,
                'node_name' => 'CORE',
                'node_type' => 'router',
                'x_position' => $centerLng - 0.015,
                'y_position' => $centerLat + 0.008,
                'icon_type' => 'router',
                'is_locked' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'device_name' => 'RTR-CORE-DEMO',
                'ip_address' => '10.10.0.1',
                'last_status' => 'OK',
                'snmp_version' => '2c',
            ],
            [
                'id' => 2,
                'device_id' => 102,
                'node_name' => 'AGG',
                'node_type' => 'switch',
                'x_position' => $centerLng + 0.000,
                'y_position' => $centerLat + 0.000,
                'icon_type' => 'switch',
                'is_locked' => 0,
                'created_at' => $now,
                'updated_at' => $now,
                'device_name' => 'SW-AGG-DEMO',
                'ip_address' => '10.10.0.2',
                'last_status' => 'OK',
                'snmp_version' => '2c',
            ],
            [
                'id' => 3,
                'device_id' => 103,
                'node_name' => 'OLT',
                // Use a known icon key (map.js nodeIcons) so the marker renders consistently.
                'node_type' => 'server',
                'x_position' => $centerLng + 0.018,
                'y_position' => $centerLat - 0.010,
                'icon_type' => 'server',
                'is_locked' => 0,
                'created_at' => $now,
                'updated_at' => $now,
                'device_name' => 'OLT-HIOSO-DEMO',
                'ip_address' => '10.10.10.1',
                'last_status' => 'OK',
                'snmp_version' => '2c',
            ],
            [
                'id' => 4,
                'device_id' => null,
                'node_name' => 'CLOUD',
                'node_type' => 'cloud',
                'x_position' => $centerLng - 0.030,
                'y_position' => $centerLat - 0.018,
                'icon_type' => 'cloud',
                'is_locked' => 0,
                'created_at' => $now,
                'updated_at' => $now,
                'device_name' => null,
                'ip_address' => null,
                'last_status' => 'OK',
                'snmp_version' => null,
            ],
        ];

        foreach ($nodes as &$n) {
            $n['status'] = $n['last_status'] ?? 'unknown';
            $n['interfaces'] = [];
            $n['interfaces_loaded'] = false;
            if ($withInterfaces && !empty($n['device_id']) && ($n['status'] === 'OK')) {
                $n['interfaces'] = array_map(function ($i) {
                    return [
                        'if_name' => $i['if_name'],
                        'if_alias' => $i['if_alias'] ?? null,
                        'if_description' => null,
                        'if_type' => null,
                        'is_sfp' => (int) ($i['is_sfp'] ?? 0),
                        'last_seen' => $i['last_seen'] ?? null,
                        'rx_power' => $i['rx_power'],
                        'tx_power' => $i['tx_power'],
                        'interface_type' => null,
                        'oper_status' => 1,
                    ];
                }, self::interfaces((int) $n['device_id']));
                $n['interfaces_loaded'] = true;
            }
        }

        return $nodes;
    }

    public static function mapLinks(): array
    {
        $now = date('Y-m-d H:i:s');
        return [
            [
                'id' => 1,
                'node_a_id' => 1,
                'node_b_id' => 2,
                'interface_a_id' => 9001,
                'interface_b_id' => 9002,
                'attenuation_db' => '16.2',
                'notes' => 'Demo link',
                'path_json' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'node_a_name' => 'CORE',
                'node_b_name' => 'AGG',
                'interface_a_name' => 'sfp-sfpplus1',
                'interface_b_name' => 'sfp-sfpplus2',
                'interface_a_status' => 1,
                'interface_b_status' => 1,
                'interface_a_rx' => -17.2,
                'interface_a_tx' => -1.1,
                'interface_b_rx' => -26.8,
                'interface_b_tx' => -2.4,
            ],
            [
                'id' => 2,
                'node_a_id' => 2,
                'node_b_id' => 3,
                'interface_a_id' => 9002,
                'interface_b_id' => 9001,
                'attenuation_db' => '24.1',
                'notes' => 'Demo uplink',
                'path_json' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'node_a_name' => 'AGG',
                'node_b_name' => 'OLT',
                'interface_a_name' => 'sfp-sfpplus2',
                'interface_b_name' => 'sfp-sfpplus1',
                'interface_a_status' => 1,
                'interface_b_status' => 1,
                'interface_a_rx' => -26.8,
                'interface_a_tx' => -2.4,
                'interface_b_rx' => -17.2,
                'interface_b_tx' => -1.1,
            ],
            [
                'id' => 3,
                'node_a_id' => 1,
                'node_b_id' => 4,
                'interface_a_id' => 9001,
                'interface_b_id' => 9001,
                'attenuation_db' => '-20.5',
                'notes' => 'Demo internet',
                'path_json' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'node_a_name' => 'CORE',
                'node_b_name' => 'CLOUD',
                'interface_a_name' => 'sfp-sfpplus1',
                'interface_b_name' => 'wan',
                'interface_a_status' => 1,
                'interface_b_status' => 1,
                'interface_a_rx' => -19.0,
                'interface_a_tx' => -2.0,
                'interface_b_rx' => -19.0,
                'interface_b_tx' => -2.0,
            ],
        ];
    }

    public static function mapDevices(): array
    {
        return array_map(function ($d) {
            return [
                'id' => $d['id'],
                'device_name' => $d['device_name'],
                'ip_address' => $d['ip_address'],
                'last_status' => $d['last_status'] ?? 'unknown',
            ];
        }, self::devices());
    }

    public static function users(int $currentUserId): array
    {
        $now = date('Y-m-d H:i:s');
        $all = [
            ['id' => 1, 'username' => 'admin-demo', 'full_name' => 'Admin Demo', 'role' => 'admin', 'is_active' => 1, 'created_at' => $now],
            ['id' => 2, 'username' => 'tech-demo', 'full_name' => 'Technician Demo', 'role' => 'technician', 'is_active' => 1, 'created_at' => $now],
            ['id' => 3, 'username' => 'viewer-demo', 'full_name' => 'Viewer Demo', 'role' => 'viewer', 'is_active' => 1, 'created_at' => $now],
            ['id' => 4, 'username' => 'disabled-demo', 'full_name' => 'Disabled Demo', 'role' => 'viewer', 'is_active' => 0, 'created_at' => $now],
        ];

        return array_values(array_filter($all, fn ($u) => (int) $u['id'] !== (int) $currentUserId));
    }

    public static function settings(): array
    {
        return [
            'bot_token' => '',
            'chat_id' => '',
            'alert_telegram_enabled' => '0',
            'alert_webui_enabled' => '1',
            'alert_interface_down' => '1',
            'alert_interface_up' => '1',
            'alert_interface_warning' => '1',
            'alert_device_down' => '1',
            'alert_device_up' => '1',
            'alert_rx_warning_high' => '-18.0',
            'alert_rx_warning_low' => '-25.0',
            'alert_rx_down_threshold' => '-40.0',
        ];
    }

    public static function alertLogs(): array
    {
        $now = time();
        $rows = [];
        for ($i = 0; $i < 24; $i++) {
            $t = $now - ($i * 300);
            $rows[] = [
                'id' => 1000 + $i,
                'created_at' => date('Y-m-d H:i:s', $t),
                'event_type' => ($i % 3 === 0) ? 'interface_warning' : (($i % 5 === 0) ? 'device_down' : 'interface_up'),
                'severity' => ($i % 5 === 0) ? 'critical' : (($i % 3 === 0) ? 'warning' : 'info'),
                'device_id' => 101,
                'device_name' => 'RTR-CORE-DEMO',
                'device_ip' => '10.10.0.1',
                'if_index' => 1,
                'if_name' => 'sfp-sfpplus1',
                'if_alias' => 'Uplink-1',
                'rx_power' => -20.5,
                'tx_power' => -2.1,
                'message' => 'Demo alert event (viewer mode)',
            ];
        }

        return $rows;
    }

    public static function securityLogsText(): string
    {
        $now = time();
        $lines = [];
        for ($i = 0; $i < 12; $i++) {
            $t = date('Y-m-d H:i:s', $now - ($i * 600));
            $lines[] = "[{$t}] [10.10.0." . (10 + $i) . "] [LOGIN_SUCCESS] user=viewer-demo msg=OK";
        }
        return implode("\n", array_reverse($lines));
    }

    public static function oltConfig(): array
    {
        return [
            'olt-hioso-demo-1' => [
                'name' => 'HIOSO-DEMO-1',
                'pons' => ['0/1', '0/2', '0/3', '0/4'],
            ],
            'olt-hioso-demo-2' => [
                'name' => 'HIOSO-DEMO-2',
                'pons' => ['0/1', '0/2', '0/3', '0/4'],
            ],
        ];
    }

    public static function oltPonData(string $pon): array
    {
        $rows = [];
        for ($i = 1; $i <= 16; $i++) {
            $onuId = $pon . ':' . $i;
            $isUp = ($i % 7) !== 0;
            $rx = $isUp ? (-18.0 - (($i % 10) * 0.7)) : null;
            $tx = $isUp ? (-2.0 - (($i % 6) * 0.4)) : null;
            $rows[] = [
                'onu_id' => $onuId,
                'mac' => sprintf('aa:bb:cc:%02x:%02x:%02x', $i, $i, $i),
                'status' => $isUp ? 'Up' : 'Down',
                'name' => "ONU-DEMO-{$i}",
                'tx_power' => $tx,
                'rx_power' => $rx,
                'temperature' => $isUp ? (30.0 + ($i % 5)) : null,
                'signal' => !$isUp ? 'offline' : ($rx < -28 ? 'critical' : ($rx < -25 ? 'warning' : 'good')),
                'uptime' => $isUp ? ($i . "d " . ($i * 2) . "h") : '-',
            ];
        }

        return [
            'pon' => $pon,
            'total' => count($rows),
            'onu' => $rows,
        ];
    }
}
