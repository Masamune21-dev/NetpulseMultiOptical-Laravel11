<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingsApiController extends Controller
{
    public function settings(Request $request)
    {
        $user = $request->session()->get('auth.user');
        $role = $user['role'] ?? '';

        if ($request->isMethod('get')) {
            if (!in_array($role, ['admin', 'technician'], true)) {
                return response()->json(['error' => 'Forbidden'], 403);
            }

            $rows = DB::table('settings')->select(['name', 'value'])->get();
            $data = [];
            foreach ($rows as $row) {
                $data[$row->name] = $row->value;
            }
            return response()->json($data);
        }

        if ($role !== 'admin') {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $data = $request->json()->all();
        foreach ($data as $k => $v) {
            DB::statement(
                "INSERT INTO settings (name, value)
                 VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE value = VALUES(value)",
                [$k, $v]
            );
        }

        return response()->json(['success' => true]);
    }

    public function telegramTest(Request $request)
    {
        $user = $request->session()->get('auth.user');
        if (($user['role'] ?? '') !== 'admin') {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
        }

        $settings = [
            'bot_token' => '',
            'chat_id' => '',
        ];

        $rows = DB::table('settings')
            ->whereIn('name', ['bot_token', 'chat_id'])
            ->get();

        foreach ($rows as $row) {
            if ($row->name === 'bot_token') {
                $settings['bot_token'] = trim((string) $row->value);
            }
            if ($row->name === 'chat_id') {
                $settings['chat_id'] = trim((string) $row->value);
            }
        }

        if ($settings['bot_token'] === '' || $settings['chat_id'] === '') {
            return response()->json(['success' => false, 'error' => 'Bot token atau chat ID belum diset'], 400);
        }

        $message = 'Test Telegram dari NetPulse MultiOptical' . "\n" . 'Time: ' . date('Y-m-d H:i:s');
        $sent = $this->telegramSendMessage($settings['bot_token'], $settings['chat_id'], $message);

        if (!$sent) {
            return response()->json(['success' => false, 'error' => 'Gagal mengirim pesan ke Telegram'], 500);
        }

        return response()->json(['success' => true]);
    }

    public function logs(Request $request)
    {
        $user = $request->session()->get('auth.user');
        if (($user['role'] ?? '') !== 'admin') {
            return response()->json(['success' => false, 'error' => 'Forbidden'], 403);
        }

        $type = $request->query('type', '');
        if ($type !== 'security') {
            return response()->json(['success' => false, 'error' => 'Invalid log type']);
        }

        $logFile = storage_path('logs/security.log');
        if (!is_file($logFile)) {
            return response()->json(['success' => true, 'data' => 'No logs yet.']);
        }

        $lines = @file($logFile, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return response()->json(['success' => false, 'error' => 'Failed to read log file']);
        }

        $tail = array_slice($lines, -200);
        return response()->json(['success' => true, 'data' => implode("\n", $tail)]);
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
