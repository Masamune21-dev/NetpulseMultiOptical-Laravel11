<?php

namespace App\Services;

class OltCollector
{
    private string $promptRegex = '/\n[^\n]*[#>]\s*$/';
    private ?string $promptLine = null;

    public function collectAllPon(array $olt): array
    {
        $fp = $this->telnetConnect($olt);
        $results = [];

        $mode = strtolower((string) ($olt['mode'] ?? 'epon'));
        if (!in_array($mode, ['epon', 'gpon'], true)) {
            $mode = 'epon';
        }
        $cmdTimeout = (int) ($olt['cmd_timeout'] ?? 12);
        if ($cmdTimeout < 6) $cmdTimeout = 6;
        if ($cmdTimeout > 60) $cmdTimeout = 60;

        foreach ($olt['pons'] as $pon) {
            $listCmd = (string) ($olt['onu_list_cmd'] ?? "show onu info {$mode} {$pon} all");
            $listRaw = $this->telnetCmd($fp, $listCmd, $cmdTimeout);
            $onus = $this->parseOnuList($listRaw);
            if (empty($onus)) {
                $this->writeDebug($olt, $pon, 'onu_list', $listCmd . "\n\n" . $listRaw);
            }

            foreach ($onus as &$onu) {
                if (($onu['status'] ?? '') !== 'Up') {
                    $onu['signal'] = 'offline';
                    continue;
                }

                $id = explode(':', $onu['onu_id'])[1] ?? null;
                if ($id === null) {
                    continue;
                }

                $ddmCmd = (string) ($olt['onu_ddm_cmd'] ?? "show onu optical-ddm {$mode} {$pon} {$id}");
                $ddmRaw = $this->telnetCmd($fp, $ddmCmd, $cmdTimeout);
                $opt = $this->parseOpticalDDM($ddmRaw);
                if (empty($opt)) {
                    // Not fatal, but helpful for diagnosing different CLI output.
                    $this->writeDebug($olt, $pon, 'ddm_' . $id, $ddmCmd . "\n\n" . $ddmRaw);
                }

                $onu += $opt;

                if (isset($onu['rx_power'])) {
                    $onu['signal'] =
                        $onu['rx_power'] < -28 ? 'critical' :
                        ($onu['rx_power'] < -25 ? 'warning' : 'good');
                }
            }

            $results[$pon] = [
                'pon' => $pon,
                'total' => count($onus),
                'onu' => $onus,
            ];
        }

        $this->telnetWrite($fp, 'quit');
        fclose($fp);

        return $results;
    }

    private function telnetConnect(array $cfg)
    {
        $fp = fsockopen($cfg['host'], $cfg['port'], $errno, $errstr, 10);
        if (!$fp) {
            throw new \RuntimeException("Telnet failed: $errstr");
        }

        stream_set_blocking($fp, false);

        // Robust login handshake: wait for prompts to avoid racing (common cause of "Password:" leaks).
        $this->readUntil($fp, ['/(username|login)\\s*:/i', '/password\\s*:/i', '/[>#]\\s*$/m'], 6);

        if (!$this->telnetWrite($fp, (string) $cfg['username'])) {
            throw new \RuntimeException('Telnet write failed');
        }
        $this->readUntil($fp, ['/password\\s*:/i', '/[>#]\\s*$/m'], 6);

        if (!$this->telnetWrite($fp, (string) $cfg['password'])) {
            throw new \RuntimeException('Telnet write failed');
        }

        $afterLogin = $this->readUntil($fp, ['/password\\s*:/i', '/[>#]\\s*$/m'], 10);
        $this->setPromptFromBuffer($afterLogin);

        // If we somehow are still at a password prompt after sending the password, fail early.
        if (preg_match('/password\\s*:/i', $afterLogin) && !preg_match('/[>#]\\s*$/m', $afterLogin)) {
            throw new \RuntimeException('Login failed (stuck at password prompt)');
        }

        $useEnable = array_key_exists('enable', $cfg) ? (bool) $cfg['enable'] : true;
        if ($useEnable) {
            // If prompt already ends with '#', we're already enabled.
            if (is_string($this->promptLine) && str_ends_with($this->promptLine, '#')) {
                // no-op
            } else {
            if (!$this->telnetWrite($fp, 'enable')) {
                throw new \RuntimeException('Telnet write failed');
            }
            $enableResp = $this->readUntil($fp, ['/password\\s*:/i', '/[>#]\\s*$/m'], 10);

            // Some OLTs require an "enable" password. If we don't answer it, subsequent commands
            // will be interpreted as the password and the collector will produce empty output.
            if (preg_match('/password\\s*:/i', $enableResp)) {
                $enablePass = (string) ($cfg['enable_password'] ?? $cfg['password'] ?? '');
                if ($enablePass === '') {
                    throw new \RuntimeException('Enable password required (set enable_password or disable enable)');
                }
                $this->telnetWrite($fp, $enablePass);
                $post = $this->readUntil($fp, ['/incorrect\\s+passw|invalid\\s+passw/i', '/password\\s*:/i', '/[>#]\\s*$/m'], 10);
                if (preg_match('/incorrect\\s+passw|invalid\\s+passw|password\\s*:/i', $post)) {
                    throw new \RuntimeException('Enable password rejected (check enable_password)');
                }
            }
            $this->setPromptFromBuffer($enableResp);
            if (isset($post)) {
                $this->setPromptFromBuffer($post);
            }
            }
        }

        // Learn prompt to make command read-loop reliable (devices vary).
        if ($this->promptLine) {
            $quoted = preg_quote($this->promptLine, '/');
            $this->promptRegex = '/\n' . $quoted . '\s*$/';
        } else {
            $this->promptRegex = $this->detectPromptRegex($fp);
        }

        return $fp;
    }

    private function readUntil($fp, array $patterns, int $timeoutSec = 6): string
    {
        $buf = '';
        $start = microtime(true);
        while (microtime(true) - $start < $timeoutSec) {
            $chunk = fread($fp, 8192);
            if ($chunk !== false && $chunk !== '') {
                $buf .= $chunk;
                $start = microtime(true);
            }

            foreach ($patterns as $rx) {
                if (@preg_match($rx, $buf)) {
                    return $buf;
                }
            }

            usleep(150000);
        }
        return $buf;
    }

    private function setPromptFromBuffer(string $buf): void
    {
        // Try to capture the last prompt-looking line.
        if (preg_match_all('/(^|\\n)([^\\n]*[>#])\\s*$/m', $buf, $m) && !empty($m[2])) {
            $line = trim((string) end($m[2]));
            if ($line !== '') {
                $this->promptLine = $line;
            }
        }
    }

    private function detectPromptRegex($fp): string
    {
        // Send a newline and use the last prompt-like line as anchor.
        $this->telnetWrite($fp, '');
        $buf = $this->telnetRead($fp, 2);
        $buf = $this->cleanCliOutput($buf);
        $lines = array_values(array_filter(array_map('trim', explode("\n", $buf))));
        $last = end($lines);

        if (is_string($last) && preg_match('/[#>]\s*$/', $last)) {
            $quoted = preg_quote($last, '/');
            return '/\n' . $quoted . '\s*$/';
        }

        // Fallback: any line ending with # or >
        return '/\n[^\n]*[#>]\s*$/';
    }

    private function telnetRead($fp, int $timeout = 2): string
    {
        $data = '';
        $start = microtime(true);

        while (microtime(true) - $start < $timeout) {
            $chunk = fread($fp, 8192);
            if ($chunk !== false && $chunk !== '') {
                $data .= $chunk;
                $start = microtime(true);
            }
            usleep(150000);
        }
        return $data;
    }

    private function telnetWrite($fp, string $cmd): bool
    {
        if (!is_resource($fp) || feof($fp)) {
            return false;
        }
        $bytes = @fwrite($fp, $cmd . "\r\n");
        if ($bytes === false) {
            return false;
        }
        usleep(300000);
        return true;
    }

    private function telnetCmd($fp, string $cmd, int $timeoutSec = 10): string
    {
        if (!$this->telnetWrite($fp, $cmd)) {
            return '';
        }

        $buf = '';
        $start = microtime(true);

        while (true) {
            $chunk = fread($fp, 8192);
            if ($chunk !== false && $chunk !== '') {
                $buf .= $chunk;
                $start = microtime(true);
            }

            // Pagination prompts vary by vendor/firmware.
            if (
                stripos($buf, 'Enter Key To Continue') !== false ||
                stripos($buf, 'Press any key to continue') !== false ||
                stripos($buf, '--More--') !== false ||
                stripos($buf, 'More:') !== false
            ) {
                $buf = str_replace(['--- Enter Key To Continue ----', '--More--'], '', $buf);
                // Space is the safest pager-continue key on many CLIs.
                $this->telnetWrite($fp, ' ');
            }

            if (preg_match($this->promptRegex, $buf)) {
                break;
            }

            if (microtime(true) - $start > $timeoutSec) {
                break;
            }

            usleep(150000);
        }

        return $this->cleanCliOutput($buf);
    }

    private function cleanCliOutput(string $text): string
    {
        $text = preg_replace('/\x1B\[[0-9;]*[A-Za-z]/', '', $text);
        $text = str_replace("\r", '', $text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        return implode("\n", array_map('trim', explode("\n", $text)));
    }

    private function parseOnuList(string $raw): array
    {
        $out = [];
        foreach (explode("\n", $raw) as $l) {
            if (!preg_match(
                '/^(\d+\/\d+:\d+)\s+([0-9a-f:]+)\s+(Up|Down)\s+(\S+)\s+(\S+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s*(.*)$/i',
                $l,
                $m
            )) {
                // Some firmware omits fields; try a looser match.
                if (!preg_match(
                    '/^(\d+\/\d+:\d+)\s+([0-9a-f:]+)\s+(Up|Down)\s+(\S+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s*(.*)$/i',
                    $l,
                    $m2
                )) {
                    continue;
                }

                // Map to the same shape, leaving firmware/chip null.
                $out[] = [
                    'onu_id' => $m2[1],
                    'mac' => $m2[2],
                    'status' => $m2[3],
                    'firmware' => null,
                    'chip' => $m2[4],
                    'ge' => (int) $m2[5],
                    'fe' => (int) $m2[6],
                    'pots' => (int) $m2[7],
                    'ctc' => $m2[8],
                    'ctc_ver' => $m2[9],
                    'activate' => $m2[10],
                    'uptime' => $m2[11],
                    'name' => trim($m2[12]),
                ];
                continue;
            }

            $out[] = [
                'onu_id' => $m[1],
                'mac' => $m[2],
                'status' => $m[3],
                'firmware' => $m[4],
                'chip' => $m[5],
                'ge' => (int) $m[6],
                'fe' => (int) $m[7],
                'pots' => (int) $m[8],
                'ctc' => $m[9],
                'ctc_ver' => $m[10],
                'activate' => $m[11],
                'uptime' => $m[12],
                'name' => trim($m[13]),
            ];
        }
        return $out;
    }

    private function writeDebug(array $olt, string $pon, string $suffix, string $content): void
    {
        try {
            $oltId = (string) ($olt['_id'] ?? '');
            if ($oltId === '') {
                // Fallback: sanitize name to a directory
                $oltId = preg_replace('/[^a-zA-Z0-9_-]+/', '_', (string) ($olt['name'] ?? 'olt'));
            }
            $ponSafe = str_replace('/', '_', $pon);
            $dir = storage_path("app/olt/{$oltId}/debug");
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $ts = date('Ymd_His');
            $file = "{$dir}/{$ts}_{$ponSafe}_{$suffix}.txt";

            // Cap size: keep first ~200KB to avoid disk bloat.
            $max = 200 * 1024;
            if (strlen($content) > $max) {
                $content = substr($content, 0, $max) . "\n\n[TRUNCATED]\n";
            }
            @file_put_contents($file, $content);
        } catch (\Throwable $e) {
            // Ignore debug failures.
        }
    }

    private function parseOpticalDDM(string $raw): array
    {
        $d = [];
        if (preg_match('/Temperature\s*:\s*([\d\.]+)/i', $raw, $m)) {
            $d['temperature'] = (float) $m[1];
        }
        if (preg_match('/Voltage\s*:\s*([\d\.]+)/i', $raw, $m)) {
            $d['voltage'] = (float) $m[1];
        }
        if (preg_match('/TxPower\s*:\s*([-\d\.]+)/i', $raw, $m)) {
            $d['tx_power'] = (float) $m[1];
        }
        if (preg_match('/RxPower\s*:\s*([-\d\.]+)/i', $raw, $m)) {
            $d['rx_power'] = (float) $m[1];
        }
        return $d;
    }
}
