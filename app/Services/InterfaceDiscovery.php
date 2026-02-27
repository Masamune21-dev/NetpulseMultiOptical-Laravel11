<?php

namespace App\Services;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\FcmService;
use Illuminate\Support\Facades\Log;

class InterfaceDiscovery
{
    private static bool $alertLogTableChecked = false;

    public function discover(int $deviceId, bool $isCli = false): array
    {
        if (!function_exists('snmp2_walk') || !function_exists('snmp2_get')) {
            return [
                'success' => false,
                'error' => 'SNMP extension not installed',
            ];
        }

        $device = DB::table('snmp_devices')->where('id', $deviceId)->first();
        if (!$device) {
            return [
                'success' => false,
                'error' => 'Device not found',
            ];
        }

        $ip = $device->ip_address;
        $community = $device->community;
        if (!$community) {
            return [
                'success' => false,
                'error' => 'SNMP community not configured',
            ];
        }

        $alertSettings = $this->loadAlertSettings();
        $alertStateFile = storage_path('app/alert_state.json');
        $alertState = $isCli ? $this->loadAlertState($alertStateFile) : [];
        $alertStateDirty = false;

        $ifIndex = @\snmp2_walk($ip, $community, '1.3.6.1.2.1.2.2.1.1');
        $ifName = @\snmp2_walk($ip, $community, '1.3.6.1.2.1.31.1.1.1.1');
        $ifDescr = @\snmp2_walk($ip, $community, '1.3.6.1.2.1.2.2.1.2');
        $ifAlias = @\snmp2_walk($ip, $community, '1.3.6.1.2.1.31.1.1.1.18');
        $ifOper = @\snmp2_walk($ip, $community, '1.3.6.1.2.1.2.2.1.8');

        // Device up/down alert (CLI only, transition based)
        if ($isCli) {
            $deviceLabel = trim(($device->device_name ?? '') . ' (' . $ip . ')');
            $timeLabel = date('Y-m-d H:i:s');
            $devKey = "dev:{$deviceId}";
            $prevDev = $alertState[$devKey] ?? null;
            $prevKnown = is_array($prevDev);
            $prevUp = $prevKnown ? (bool) ($prevDev['device_up'] ?? false) : true;
            $nowUp = (bool) ($ifIndex && $ifName);

            $alertState[$devKey] = [
                'device_up' => $nowUp,
                'last_check' => $timeLabel,
            ];
            $alertStateDirty = true;

            if ($prevKnown && $prevUp && !$nowUp && ($alertSettings['device_down'] ?? true)) {
                $msg = "🔴 DEVICE DOWN\n📟 Device: {$deviceLabel}\n🕒 Time: {$timeLabel}";
                $this->emitAlert(
                    $alertSettings,
                    [
                        'device_id' => $deviceId,
                        'device_name' => (string) ($device->device_name ?? ''),
                        'device_ip' => (string) $ip,
                    ],
                    null,
                    'device_down',
                    'critical',
                    "Device down: {$deviceLabel}",
                    $msg
                );
            } elseif ($prevKnown && !$prevUp && $nowUp && ($alertSettings['device_up'] ?? true)) {
                $msg = "🟢 DEVICE UP\n📟 Device: {$deviceLabel}\n🕒 Time: {$timeLabel}";
                $this->emitAlert(
                    $alertSettings,
                    [
                        'device_id' => $deviceId,
                        'device_name' => (string) ($device->device_name ?? ''),
                        'device_ip' => (string) $ip,
                    ],
                    null,
                    'device_up',
                    'info',
                    "Device up: {$deviceLabel}",
                    $msg
                );
            }
        }

        if (!$ifIndex || !$ifName) {
            if ($isCli && $alertStateDirty) {
                $this->saveAlertState($alertStateFile, $alertState);
            }
            if ($isCli) {
                return [
                    'success' => false,
                    'error' => "SKIP device ID: {$deviceId} (IF-MIB unreachable)",
                ];
            }
            return [
                'success' => false,
                'error' => 'Cannot read IF-MIB',
            ];
        }

        $ifNameMap = [];
        foreach ($ifIndex as $i => $raw) {
            $idx = (int) filter_var($raw, FILTER_SANITIZE_NUMBER_INT);
            $name = trim(str_replace(['STRING:', '"'], '', $ifName[$i] ?? ''));
            if ($name !== '') {
                $ifNameMap[$name] = $idx;
            }
        }

        $opticalMap = [];
        $opticalNames = @\snmp2_walk(
            $ip,
            $community,
            '1.3.6.1.4.1.14988.1.1.19.1.1.2'
        );
        if ($opticalNames === false) {
            $opticalNames = [];
        }

        foreach ($opticalNames as $val) {
            $optIfName = trim(str_replace(['STRING:', '"'], '', $val));
            if (!isset($ifNameMap[$optIfName])) {
                continue;
            }

            $ifIdx = $ifNameMap[$optIfName];
            $txRaw = \snmp2_get(
                $ip,
                $community,
                "1.3.6.1.4.1.14988.1.1.19.1.1.9.$ifIdx"
            );
            $rxRaw = \snmp2_get(
                $ip,
                $community,
                "1.3.6.1.4.1.14988.1.1.19.1.1.10.$ifIdx"
            );

            if ($txRaw !== false && $rxRaw !== false) {
                $txMatch = preg_match('/-?\d+/', $txRaw, $m1);
                $rxMatch = preg_match('/-?\d+/', $rxRaw, $m2);
                if ($txMatch && $rxMatch) {
                    $opticalMap[$optIfName] = [
                        'tx' => $m1[0] / 1000,
                        'rx' => $m2[0] / 1000,
                        'oper' => 1,
                    ];
                }
            }
        }

        $inserted = 0;
        $sfpCount = 0;
        $downSfpCount = 0;

        foreach ($ifIndex as $i => $raw) {
            $ifIdx = (int) filter_var($raw, FILTER_SANITIZE_NUMBER_INT);
            $name = trim(str_replace(['STRING:', '"'], '', $ifName[$i] ?? ''));
            $alias = trim(str_replace(['STRING:', '"'], '', $ifAlias[$i] ?? $name));
            $desc = trim(str_replace(['STRING:', '"'], '', $ifDescr[$i] ?? $name));

            $oper = 2;
            if (isset($ifOper[$i])) {
                $oper = (int) filter_var($ifOper[$i], FILTER_SANITIZE_NUMBER_INT);
            }

            $isSfp = 0;
            $type = 'other';
            $tx = null;
            $rx = null;

            if (
                stripos($name, 'sfp') !== false ||
                stripos($name, 'xgigabit') !== false ||
                stripos($name, '100ge') !== false ||
                stripos($name, 'gpon') !== false ||
                stripos($name, 'xpon') !== false
            ) {
                $isSfp = 1;

                if (stripos($name, '100ge') !== false) {
                    $type = 'QSFP+';
                } elseif (stripos($name, 'gpon') !== false || stripos($name, 'xpon') !== false) {
                    $type = 'PON';
                } else {
                    $type = 'SFP+';
                }
            }

            if ($isSfp) {
                if ($oper === 1) {
                    if (isset($opticalMap[$name])) {
                        $tx = $opticalMap[$name]['tx'];
                        $rx = $opticalMap[$name]['rx'];
                    } elseif (stripos((string) $device->device_name, 'huawei') !== false) {
                        [$tx, $rx] = $this->readHuaweiOptics($name);
                    }
                } else {
                    $rx = -40.00;
                    $tx = null;
                    $downSfpCount++;
                }
            }

            if ($isCli && $isSfp) {
                $deviceLabel = trim(($device->device_name ?? '') . ' (' . $ip . ')');
                $ifaceComment = $alias !== '' ? $alias : ($desc !== '' ? $desc : '');
                $ifaceLabel = $ifaceComment !== '' ? "{$name} ({$ifaceComment})" : $name;
                $timeLabel = date('Y-m-d H:i:s');

                $stateKey = $deviceId . ':' . $ifIdx;
                $prevState = $alertState[$stateKey] ?? null;
                $prevKnown = is_array($prevState);
                $prevLinkUp = is_array($prevState) ? (bool) ($prevState['link_up'] ?? false) : false;
                $prevWarnLevel = 'none';
                if (is_array($prevState)) {
                    if (isset($prevState['warn_level'])) {
                        $prevWarnLevel = (string) $prevState['warn_level'];
                    } elseif (!empty($prevState['warn'])) {
                        $prevWarnLevel = 'warning';
                    }
                }
                $prevHadOptic = is_array($prevState) ? (bool) ($prevState['had_optic'] ?? false) : false;

                $hasOpticUp = ($oper == 1) && ($rx !== null || $tx !== null);
                $downThreshold = is_numeric($alertSettings['rx_down_threshold'] ?? null)
                    ? (float) $alertSettings['rx_down_threshold']
                    : -40.0;
                $warnHigh = is_numeric($alertSettings['rx_warn_high'] ?? null)
                    ? (float) $alertSettings['rx_warn_high']
                    : -18.0;
                $warnLow = is_numeric($alertSettings['rx_warn_low'] ?? null)
                    ? (float) $alertSettings['rx_warn_low']
                    : -25.0;
                if ($warnLow > $warnHigh) {
                    [$warnLow, $warnHigh] = [$warnHigh, $warnLow];
                }

                $isDownByRx = ($rx === null || (is_numeric($rx) && (float) $rx <= $downThreshold));
                $linkUp = ($oper == 1) && !$isDownByRx;
                $nowWarnLevel = 'none';
                if ($linkUp && is_numeric($rx)) {
                    $rxF = (float) $rx;
                    if ($rxF <= $warnHigh && $rxF > $downThreshold) {
                        $nowWarnLevel = ($rxF < $warnLow) ? 'critical' : 'warning';
                    }
                }
                $warnNow = $nowWarnLevel !== 'none';

                $alertState[$stateKey] = [
                    'link_up' => $linkUp,
                    'warn' => $warnNow, // legacy
                    'warn_level' => $nowWarnLevel,
                    'had_optic' => ($hasOpticUp || $prevHadOptic),
                ];
                $alertStateDirty = true;

                $deviceMeta = [
                    'device_id' => $deviceId,
                    'device_name' => (string) ($device->device_name ?? ''),
                    'device_ip' => (string) $ip,
                ];
                $ifaceMeta = [
                    'if_index' => $ifIdx,
                    'if_name' => $name,
                    'if_alias' => $alias,
                    'rx_power' => $rx,
                    'tx_power' => $tx,
                ];

                if (
                    $prevKnown && $prevLinkUp && !$linkUp
                    && ($prevHadOptic || $hasOpticUp)
                    && ($alertSettings['interface_down'] ?? true)
                ) {
                    $tg = "🔴 LINK DOWN\n📟 Device: {$deviceLabel}\n🔌 Interface: {$ifaceLabel}\n🕒 Time: {$timeLabel}";
                    $this->emitAlert(
                        $alertSettings,
                        $deviceMeta,
                        $ifaceMeta,
                        'interface_down',
                        'critical',
                        "Interface down: {$deviceLabel} / {$ifaceLabel}",
                        $tg
                    );
                } elseif (
                    $prevKnown && !$prevLinkUp && $linkUp
                    && ($prevHadOptic || $hasOpticUp)
                    && ($alertSettings['interface_up'] ?? true)
                ) {
                    $tg = "🟢 LINK UP\n📟 Device: {$deviceLabel}\n🔌 Interface: {$ifaceLabel}\n📡 RX: " . ($rx !== null ? "{$rx} dBm" : 'N/A') . "\n🕒 Time: {$timeLabel}";
                    $this->emitAlert(
                        $alertSettings,
                        $deviceMeta,
                        $ifaceMeta,
                        'interface_up',
                        'info',
                        "Interface up: {$deviceLabel} / {$ifaceLabel} (RX " . ($rx !== null ? "{$rx} dBm" : 'N/A') . ")",
                        $tg
                    );
                }

                if (
                    $prevKnown && $warnNow && $prevWarnLevel !== $nowWarnLevel
                    && ($alertSettings['interface_warning'] ?? true)
                ) {
                    $tg = "🟡 RX WARNING\n📟 Device: {$deviceLabel}\n🔌 Interface: {$ifaceLabel}\n📡 RX: " . ($rx !== null ? "{$rx} dBm" : 'N/A') . "\n🕒 Time: {$timeLabel}";
                    $this->emitAlert(
                        $alertSettings,
                        $deviceMeta,
                        $ifaceMeta,
                        'interface_warning',
                        $nowWarnLevel === 'critical' ? 'critical' : 'warning',
                        "RX warning: {$deviceLabel} / {$ifaceLabel} (RX " . ($rx !== null ? "{$rx} dBm" : 'N/A') . ")",
                        $tg
                    );
                }
            }

            DB::statement(
                "INSERT INTO interfaces
                (device_id, if_index, if_name, if_alias, if_description, optical_index, rx_power, tx_power, oper_status, last_seen, is_sfp, interface_type)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)
                ON DUPLICATE KEY UPDATE
                    optical_index=VALUES(optical_index),
                    rx_power=VALUES(rx_power),
                    tx_power=VALUES(tx_power),
                    oper_status=VALUES(oper_status),
                    last_seen=NOW(),
                    is_sfp=VALUES(is_sfp),
                    interface_type=VALUES(interface_type)",
                [
                    $deviceId,
                    $ifIdx,
                    $name,
                    $alias,
                    $desc,
                    null,
                    $rx,
                    $tx,
                    $oper,
                    $isSfp,
                    $type,
                ]
            );

            $inserted++;
            if ($isSfp) {
                $sfpCount++;
            }

            if ($isSfp && $rx !== null) {
                $loss = ($tx !== null && $rx !== null) ? ($tx - $rx) : null;
                DB::table('interface_stats')->insert([
                    'device_id' => $deviceId,
                    'if_index' => $ifIdx,
                    'tx_power' => $tx,
                    'rx_power' => $rx,
                    'loss' => $loss,
                    'created_at' => now(),
                ]);

                DB::table('interfaces')
                    ->where('device_id', $deviceId)
                    ->where('if_index', $ifIdx)
                    ->update([
                        'rx_power' => $rx,
                        'tx_power' => $tx,
                        'oper_status' => $oper,
                        'updated_at' => now(),
                    ]);
            }
        }

        if ($isCli && $alertStateDirty) {
            $this->saveAlertState($alertStateFile, $alertState);
        }

        return [
            'success' => true,
            'inserted' => $inserted,
            'sfp_count' => $sfpCount,
            'sfp_down_count' => $downSfpCount,
            'optical_found' => count($opticalMap),
            'message' => "Discover OK: {$inserted} interfaces ({$sfpCount} SFP/QSFP, {$downSfpCount} down)",
        ];
    }

    private function loadTelegramSettings(): array
    {
        // Backward compatibility shim (older callers)
        $s = $this->loadAlertSettings();
        return [
            'bot_token' => $s['bot_token'] ?? '',
            'chat_id' => $s['chat_id'] ?? '',
            'rx_threshold' => (float) ($s['rx_warn_low'] ?? -25.0),
        ];
    }

    private function loadAlertSettings(): array
    {
        $settings = [
            'bot_token' => '',
            'chat_id' => '',

            // Channel toggles (default enabled to preserve old behavior)
            'telegram_enabled' => true,
            'webui_enabled' => true,

            // Event toggles (default enabled)
            'interface_down' => true,
            'interface_up' => true,
            'interface_warning' => true,
            'device_down' => true,
            'device_up' => true,

            // Thresholds
            'rx_warn_high' => -18.0,
            'rx_warn_low' => -25.0,
            'rx_down_threshold' => -40.0,
        ];

        $keys = [
            'bot_token',
            'chat_id',
            'alert_telegram_enabled',
            'alert_webui_enabled',
            'alert_interface_down',
            'alert_interface_up',
            'alert_interface_warning',
            'alert_device_down',
            'alert_device_up',
            'alert_rx_warning_high',
            'alert_rx_warning_low',
            'alert_rx_down_threshold',
        ];

        $rows = DB::table('settings')->whereIn('name', $keys)->get();
        foreach ($rows as $row) {
            $name = (string) $row->name;
            $val = $row->value;

            if ($name === 'bot_token') {
                $settings['bot_token'] = trim((string) $val);
                continue;
            }
            if ($name === 'chat_id') {
                $settings['chat_id'] = trim((string) $val);
                continue;
            }

            if ($name === 'alert_telegram_enabled') {
                $settings['telegram_enabled'] = trim((string) $val) !== '0';
                continue;
            }
            if ($name === 'alert_webui_enabled') {
                $settings['webui_enabled'] = trim((string) $val) !== '0';
                continue;
            }

            if ($name === 'alert_interface_down') {
                $settings['interface_down'] = trim((string) $val) !== '0';
                continue;
            }
            if ($name === 'alert_interface_up') {
                $settings['interface_up'] = trim((string) $val) !== '0';
                continue;
            }
            if ($name === 'alert_interface_warning') {
                $settings['interface_warning'] = trim((string) $val) !== '0';
                continue;
            }
            if ($name === 'alert_device_down') {
                $settings['device_down'] = trim((string) $val) !== '0';
                continue;
            }
            if ($name === 'alert_device_up') {
                $settings['device_up'] = trim((string) $val) !== '0';
                continue;
            }

            if ($name === 'alert_rx_warning_high' && is_numeric($val)) {
                $settings['rx_warn_high'] = (float) $val;
                continue;
            }
            if ($name === 'alert_rx_warning_low' && is_numeric($val)) {
                $settings['rx_warn_low'] = (float) $val;
                continue;
            }
            if ($name === 'alert_rx_down_threshold' && is_numeric($val)) {
                $settings['rx_down_threshold'] = (float) $val;
                continue;
            }
        }

        return $settings;
    }

    private function emitAlert(
        array $settings,
        array $deviceMeta,
        ?array $ifaceMeta,
        string $eventType,
        string $severity,
        string $logMessage,
        string $telegramText = ''
    ): void {
        // Web UI log
        if (($settings['webui_enabled'] ?? true) === true) {
            try {
                $this->ensureAlertLogTable();
                if (Schema::hasTable('alert_logs')) {
                    $fingerprintBase = $eventType . '|' . ($deviceMeta['device_id'] ?? '') . '|' . ($ifaceMeta['if_index'] ?? '');
                    DB::table('alert_logs')->insert([
                        'event_type' => $eventType,
                        'severity' => $severity,
                        'device_id' => $deviceMeta['device_id'] ?? null,
                        'device_name' => $deviceMeta['device_name'] ?? null,
                        'device_ip' => $deviceMeta['device_ip'] ?? null,
                        'if_index' => $ifaceMeta['if_index'] ?? null,
                        'if_name' => $ifaceMeta['if_name'] ?? null,
                        'if_alias' => $ifaceMeta['if_alias'] ?? null,
                        'rx_power' => $ifaceMeta['rx_power'] ?? null,
                        'tx_power' => $ifaceMeta['tx_power'] ?? null,
                        'message' => $logMessage,
                        'context' => json_encode([
                            'device' => $deviceMeta,
                            'iface' => $ifaceMeta,
                        ]),
                        'fingerprint' => hash('sha256', $fingerprintBase),
                    ]);
                }
            } catch (\Throwable $e) {
                // Avoid breaking polling due to logging failures.
            }
        }

        // Mobile push (best-effort)
        try {
            $this->sendMobilePush($severity, $eventType, $logMessage, $deviceMeta, $ifaceMeta);
        } catch (\Throwable $e) {
            // Do not break polling if push fails.
        }

        // Telegram
        if (($settings['telegram_enabled'] ?? true) !== true) {
            return;
        }
        $botToken = trim((string) ($settings['bot_token'] ?? ''));
        $chatId = trim((string) ($settings['chat_id'] ?? ''));
        if ($botToken === '' || $chatId === '' || trim($telegramText) === '') {
            return;
        }
        $this->telegramSendMessage($botToken, $chatId, $telegramText);
    }

    private function sendMobilePush(
        string $severity,
        string $eventType,
        string $logMessage,
        array $deviceMeta,
        ?array $ifaceMeta
    ): void {
        if (!Schema::hasTable('device_tokens')) {
            return;
        }

        $tokens = DB::table('device_tokens')
            ->select(['token', 'user_id', 'last_seen_at'])
            ->whereNotNull('token')
            ->orderByDesc('last_seen_at')
            ->get();

        if ($tokens->isEmpty()) {
            return;
        }

        $prefsCache = [];
        $severityRank = [
            'info' => 1,
            'warning' => 2,
            'critical' => 3,
        ];
        $sevVal = $severityRank[strtolower($severity)] ?? 1;
        $title = strtoupper($severity) . ' • ' . str_replace('_', ' ', $eventType);
        $body = $logMessage;

        /** @var FcmService $fcm */
        $fcm = app(FcmService::class);

        foreach ($tokens as $row) {
            $userId = (int) ($row->user_id ?? 0);
            $token = (string) ($row->token ?? '');
            if ($userId <= 0 || $token === '') {
                continue;
            }

            if (!array_key_exists($userId, $prefsCache)) {
                $prefsCache[$userId] = $this->loadMobileAlertPref($userId);
            }

            $pref = $prefsCache[$userId];
            if (($pref['push_enabled'] ?? true) !== true) {
                continue;
            }
            $min = strtolower((string) ($pref['severity_min'] ?? 'warning'));
            $minVal = $severityRank[$min] ?? 2;
            if ($sevVal < $minVal) {
                continue;
            }

            try {
                $fcm->sendToToken(
                    deviceToken: $token,
                    title: $title,
                    body: $body,
                    data: [
                        'kind' => 'alert',
                        'severity' => strtolower($severity),
                        'event_type' => $eventType,
                        'device_id' => (string) ($deviceMeta['device_id'] ?? ''),
                        'if_index' => (string) ($ifaceMeta['if_index'] ?? ''),
                        'ts' => date('c'),
                    ],
                );
            } catch (\Throwable $e) {
                Log::warning('FCM push failed', [
                    'user_id' => $userId,
                    'token_prefix' => substr($token, 0, 16),
                    'error' => $e->getMessage(),
                ]);
                continue;
            }
        }
    }

    private function loadMobileAlertPref(int $userId): array
    {
        if (!Schema::hasTable('settings')) {
            return [
                'push_enabled' => true,
                'severity_min' => 'warning',
            ];
        }

        $key = 'mobile_alert_pref_user_' . $userId;
        $val = DB::table('settings')->where('name', $key)->value('value');
        $decoded = $val ? json_decode((string) $val, true) : null;
        if (!is_array($decoded)) {
            return [
                'push_enabled' => true,
                'severity_min' => 'warning',
            ];
        }
        return $decoded;
    }

    private function ensureAlertLogTable(): void
    {
        if (self::$alertLogTableChecked) {
            return;
        }
        self::$alertLogTableChecked = true;

        try {
            if (Schema::hasTable('alert_logs')) {
                return;
            }

            Schema::create('alert_logs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->timestamp('created_at')->useCurrent();

                $table->string('event_type', 64)->index();
                $table->string('severity', 16)->index();

                $table->unsignedBigInteger('device_id')->nullable()->index();
                $table->string('device_name', 190)->nullable();
                $table->string('device_ip', 64)->nullable();

                $table->unsignedInteger('if_index')->nullable()->index();
                $table->string('if_name', 190)->nullable();
                $table->string('if_alias', 190)->nullable();

                $table->decimal('rx_power', 8, 3)->nullable();
                $table->decimal('tx_power', 8, 3)->nullable();

                $table->text('message');
                // Use JSON when supported; on older MariaDB this is typically an alias to LONGTEXT anyway.
                $table->json('context')->nullable();

                $table->string('fingerprint', 64)->nullable()->index();
            });
        } catch (\Throwable $e) {
            // Do not fail polling if schema cannot be created (permissions, etc).
        }
    }

    private function readHuaweiOptics(string $port): array
    {
        $script = base_path('scripts/huawei_telnet_expect.sh');
        if (!is_file($script)) {
            return [null, null];
        }
        $cmd = $script . ' ' . escapeshellarg($port);
        $out = [];
        $rc = 0;
        exec("timeout 20s $cmd 2>&1", $out, $rc);
        if ($rc !== 0) {
            return [null, null];
        }
        $tx = null;
        $rx = null;
        foreach ($out as $line) {
            if (strpos($line, 'TX=') === 0) {
                $tx = floatval(substr($line, 3));
            }
            if (strpos($line, 'RX=') === 0) {
                $rx = floatval(substr($line, 3));
            }
        }
        return [$tx, $rx];
    }

    private function loadAlertState(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        $fp = fopen($path, 'r');
        if (!$fp) {
            return [];
        }
        flock($fp, LOCK_SH);
        $raw = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    private function saveAlertState(string $path, array $state): void
    {
        $fp = fopen($path, 'c+');
        if (!$fp) {
            return;
        }
        flock($fp, LOCK_EX);
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($state));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    private function telegramSendMessage(string $botToken, string $chatId, string $text): bool
    {
        if ($botToken === '' || $chatId === '' || $text === '') {
            return false;
        }

        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
        $payload = http_build_query([
            'chat_id' => $chatId,
            'text' => $text,
            'disable_web_page_preview' => 1,
        ]);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $result = curl_exec($ch);
            $ok = ($result !== false);
            curl_close($ch);
            return $ok;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $payload,
                'timeout' => 10,
            ],
        ]);
        $result = @file_get_contents($url, false, $context);
        return ($result !== false);
    }
}
