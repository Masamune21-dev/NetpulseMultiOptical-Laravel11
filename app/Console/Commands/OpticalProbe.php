<?php

namespace App\Console\Commands;

use App\Services\Optical\HuaweiDriver;
use App\Services\Optical\OpticalDriverResolver;
use App\Services\Optical\SnmpSession;
use App\Support\Secret;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Baca daya optik semua perangkat TANPA menulis statistik, status, maupun alert.
 *
 * Dipakai untuk membuktikan paritas saat jalur pembacaan optik diubah: jalankan
 * `--mode=legacy` (jalur lama yang dibekukan di {@see legacyMaps()}) dan `--mode=driver`
 * (lapisan driver baru), lalu bandingkan kedua berkas JSON dengan `--compare`.
 *
 * Keluaran tidak pernah memuat community SNMP. IP dan nama perangkat hanya ditulis ke
 * berkas lokal yang diminta operator, bukan ke log.
 */
class OpticalProbe extends Command
{
    protected $signature = 'optical:probe
        {--mode=driver : legacy | driver}
        {--device= : Satu device id saja}
        {--out= : Tulis hasil JSON ke berkas ini}
        {--compare=* : Dua berkas JSON untuk dibandingkan (legacy lalu driver)}
        {--tolerance=0.5 : Selisih dBm yang masih dianggap sama (pembacaan hidup berfluktuasi)}';

    protected $description = 'Baca daya optik (baca-saja) untuk uji paritas driver optik';

    public function handle(): int
    {
        $compare = (array) $this->option('compare');
        if (count($compare) === 2) {
            return $this->compare($compare[0], $compare[1], (float) $this->option('tolerance'));
        }

        if (!function_exists('snmp2_walk')) {
            $this->error('SNMP extension not installed');
            return self::FAILURE;
        }

        $mode = (string) $this->option('mode');
        $query = DB::table('snmp_devices')->where('is_active', 1)->orderBy('id');
        if ($this->option('device')) {
            $query->where('id', (int) $this->option('device'));
        }

        $result = [];
        foreach ($query->get() as $device) {
            $community = Secret::reveal($device->community);
            if (!$community) {
                continue;
            }
            $session = new SnmpSession((string) $device->ip_address, $community);
            $ifNameMap = $session->ifNameMap();
            if ($ifNameMap === null) {
                $result[$device->id] = ['driver' => null, 'error' => 'unreachable', 'optics' => []];
                $this->line("#{$device->id}: tidak menjawab");
                continue;
            }

            if ($mode === 'legacy') {
                $optics = $this->legacyMaps($device, $session, $ifNameMap);
                $driver = 'legacy';
            } else {
                $resolved = app(OpticalDriverResolver::class)->read($device, $session, $ifNameMap);
                $optics = $resolved['optics'];
                $driver = implode('+', $resolved['drivers']);
            }

            ksort($optics);
            $result[$device->id] = ['driver' => $driver, 'optics' => $optics];
            $this->line(sprintf('#%d: %s, %d interface optik', $device->id, $driver, count($optics)));
        }

        if ($out = $this->option('out')) {
            file_put_contents($out, json_encode($result, JSON_PRETTY_PRINT));
            @chmod($out, 0600);
            $this->info("Hasil ditulis ke {$out}");
        }

        return self::SUCCESS;
    }

    /**
     * Jalur lama, dibekukan persis seperti di InterfaceDiscovery sebelum 24 Sep 2026:
     * walk MikroTik untuk SETIAP perangkat, lalu peta Huawei hanya bila nama perangkat
     * memuat huawei/quidway/cloudengine. Hasil digabung dengan prioritas MikroTik.
     */
    private function legacyMaps(object $device, SnmpSession $session, array $ifNameMap): array
    {
        $opticalMap = [];
        foreach ($session->walk('1.3.6.1.4.1.14988.1.1.19.1.1.2') as $val) {
            $optIfName = trim(str_replace(['STRING:', '"'], '', $val));
            if (!isset($ifNameMap[$optIfName])) {
                continue;
            }
            $ifIdx = $ifNameMap[$optIfName];
            $txRaw = $session->get("1.3.6.1.4.1.14988.1.1.19.1.1.9.$ifIdx");
            $rxRaw = $session->get("1.3.6.1.4.1.14988.1.1.19.1.1.10.$ifIdx");
            if ($txRaw !== false && $rxRaw !== false
                && preg_match('/-?\d+/', $txRaw, $m1) && preg_match('/-?\d+/', $rxRaw, $m2)) {
                $opticalMap[$optIfName] = ['rx' => $m2[0] / 1000, 'tx' => $m1[0] / 1000];
            }
        }

        $name = (string) ($device->device_name ?? '');
        if (stripos($name, 'huawei') !== false || stripos($name, 'quidway') !== false || stripos($name, 'cloudengine') !== false) {
            // Sejak 24 Sep 2026 kode Huawei lama hidup di HuaweiDriver (dipindahkan apa adanya);
            // baseline paritas sebelum pemindahan diambil dengan kode asli di InterfaceDiscovery.
            foreach ((new HuaweiDriver())->read($session, $ifNameMap) as $ifName => $row) {
                $opticalMap[$ifName] ??= ['rx' => $row['rx'], 'tx' => $row['tx']];
            }
        }

        return $opticalMap;
    }

    private function compare(string $legacyFile, string $driverFile, float $tolerance): int
    {
        $a = json_decode((string) file_get_contents($legacyFile), true) ?: [];
        $b = json_decode((string) file_get_contents($driverFile), true) ?: [];

        $stats = ['devices' => 0, 'interfaces_legacy' => 0, 'identical' => 0, 'within_tolerance' => 0,
            'missing_in_driver' => 0, 'beyond_tolerance' => 0, 'new_in_driver' => 0];
        $problems = [];

        foreach ($a as $deviceId => $row) {
            $stats['devices']++;
            $old = $row['optics'] ?? [];
            $new = $b[$deviceId]['optics'] ?? [];
            foreach ($old as $ifName => $val) {
                $stats['interfaces_legacy']++;
                if (!array_key_exists($ifName, $new)) {
                    $stats['missing_in_driver']++;
                    $problems[] = "#{$deviceId} {$ifName}: hilang di driver";
                    continue;
                }
                $same = true;
                $close = true;
                foreach (['rx', 'tx'] as $k) {
                    $x = $val[$k] ?? null;
                    $y = $new[$ifName][$k] ?? null;
                    if ($x === $y || ($x !== null && $y !== null && abs($x - $y) < 1e-9)) {
                        continue;
                    }
                    $same = false;
                    if ($x === null || $y === null || abs($x - $y) > $tolerance) {
                        $close = false;
                    }
                }
                if ($same) {
                    $stats['identical']++;
                } elseif ($close) {
                    $stats['within_tolerance']++;
                } else {
                    $stats['beyond_tolerance']++;
                    $problems[] = "#{$deviceId} {$ifName}: " . json_encode($val) . ' → ' . json_encode($new[$ifName]);
                }
            }
            foreach (array_diff_key($new, $old) as $ifName => $_) {
                $stats['new_in_driver']++;
            }
        }

        $this->table(array_keys($stats), [array_values($stats)]);
        foreach ($problems as $p) {
            $this->warn($p);
        }

        return ($stats['missing_in_driver'] + $stats['beyond_tolerance']) === 0 ? self::SUCCESS : self::FAILURE;
    }
}
