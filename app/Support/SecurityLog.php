<?php

namespace App\Support;

/**
 * Penulis storage/logs/security.log (format lama dipertahankan agar
 * tab "Security Logs" di Settings tetap terbaca).
 */
final class SecurityLog
{
    public static function write(string $event, string $username, ?string $ip, string $message): void
    {
        $logDir = storage_path('logs');
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0750, true);
        }

        $line = sprintf(
            "[%s] [%s] [%s] user=%s msg=%s",
            date('Y-m-d H:i:s'),
            ($ip !== null && $ip !== '') ? $ip : '-',
            $event,
            $username !== '' ? $username : '-',
            $message
        );

        @file_put_contents($logDir . DIRECTORY_SEPARATOR . 'security.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
