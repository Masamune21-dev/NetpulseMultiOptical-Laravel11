<?php

namespace App\Http\Controllers;

use App\Services\FcmService;
use App\Support\ViewerDummyData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SettingsApiController extends Controller
{
    public function settings(Request $request)
    {
        $user = $request->session()->get('auth.user');
        $role = $user['role'] ?? '';

        if ($request->isMethod('get')) {
            if (ViewerDummyData::isViewer($request)) {
                return response()->json(ViewerDummyData::settings());
            }

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

    public function mobileDevices(Request $request)
    {
        $user = $request->session()->get('auth.user');
        if (($user['role'] ?? '') !== 'admin') {
            return response()->json(['success' => false, 'error' => 'Forbidden'], 403);
        }

        if (!Schema::hasTable('device_tokens')) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $query = DB::table('device_tokens as dt')
            ->select([
                'dt.id',
                'dt.user_id',
                'dt.platform',
                'dt.device_name',
                'dt.last_seen_at',
                'dt.created_at',
                'dt.token',
            ])
            ->orderByDesc('dt.last_seen_at')
            ->orderByDesc('dt.id');

        // Best-effort join with users — schema differs across deployments
        // (some installs have `name`, others `full_name` + `username`).
        if (Schema::hasTable('users')) {
            $userCols = Schema::getColumnListing('users');
            $nameCol = null;
            foreach (['full_name', 'name', 'username'] as $candidate) {
                if (in_array($candidate, $userCols, true)) {
                    $nameCol = $candidate;
                    break;
                }
            }
            if ($nameCol) {
                $query->leftJoin('users', 'users.id', '=', 'dt.user_id')
                    ->addSelect('users.' . $nameCol . ' as user_name');
            }
        }

        $rows = $query->limit(200)->get();

        $data = $rows->map(function ($row) {
            $token = (string) ($row->token ?? '');
            $tokenPreview = $token === ''
                ? ''
                : substr($token, 0, 12) . '…' . substr($token, -6);

            return [
                'id' => (int) $row->id,
                'user_id' => (int) ($row->user_id ?? 0),
                'user_name' => $row->user_name ?? null,
                'platform' => $row->platform,
                'device_name' => $row->device_name,
                'token_preview' => $tokenPreview,
                'last_seen_at' => $row->last_seen_at,
                'created_at' => $row->created_at,
            ];
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function mobilePushTargets(Request $request)
    {
        $user = $request->session()->get('auth.user');
        if (($user['role'] ?? '') !== 'admin') {
            return response()->json(['success' => false, 'error' => 'Forbidden'], 403);
        }

        if (!Schema::hasTable('device_tokens')) {
            return response()->json(['success' => true, 'data' => ['total_devices' => 0, 'users' => []]]);
        }

        $totalDevices = DB::table('device_tokens')->whereNotNull('token')->count();

        $userIds = DB::table('device_tokens')
            ->whereNotNull('token')
            ->distinct()
            ->pluck('user_id')
            ->filter(fn ($v) => (int) $v > 0)
            ->values();

        $users = [];
        if ($userIds->isNotEmpty() && Schema::hasTable('users')) {
            $userCols = Schema::getColumnListing('users');
            $nameCol = null;
            foreach (['full_name', 'name', 'username'] as $candidate) {
                if (in_array($candidate, $userCols, true)) {
                    $nameCol = $candidate;
                    break;
                }
            }

            $rows = DB::table('users')->whereIn('id', $userIds)->get();
            foreach ($rows as $row) {
                $users[] = [
                    'id' => (int) $row->id,
                    'name' => $nameCol ? ($row->{$nameCol} ?? null) : null,
                ];
            }
        } else {
            foreach ($userIds as $uid) {
                $users[] = ['id' => (int) $uid, 'name' => null];
            }
        }

        usort($users, fn ($a, $b) => strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));

        return response()->json([
            'success' => true,
            'data' => [
                'total_devices' => $totalDevices,
                'users' => $users,
            ],
        ]);
    }

    public function sendMobilePush(Request $request, FcmService $fcm)
    {
        $user = $request->session()->get('auth.user');
        if (($user['role'] ?? '') !== 'admin') {
            return response()->json(['success' => false, 'error' => 'Forbidden'], 403);
        }

        $title = trim((string) $request->json('title', ''));
        $body = trim((string) $request->json('body', ''));
        $target = (string) $request->json('target', 'all');
        $userId = (int) $request->json('user_id', 0);

        if ($title === '' || $body === '') {
            return response()->json(['success' => false, 'error' => 'Title dan body wajib diisi'], 422);
        }
        if (mb_strlen($title) > 120 || mb_strlen($body) > 1000) {
            return response()->json(['success' => false, 'error' => 'Title max 120 / body max 1000 karakter'], 422);
        }

        if (!Schema::hasTable('device_tokens')) {
            return response()->json(['success' => false, 'error' => 'No device tokens registered'], 404);
        }

        $query = DB::table('device_tokens')->whereNotNull('token');
        if ($target === 'user') {
            if ($userId <= 0) {
                return response()->json(['success' => false, 'error' => 'user_id wajib jika target=user'], 422);
            }
            $query->where('user_id', $userId);
        }

        $tokens = $query->pluck('token');
        if ($tokens->isEmpty()) {
            return response()->json(['success' => false, 'error' => 'Tidak ada device terdaftar untuk target ini'], 404);
        }

        $sent = 0;
        $failed = 0;
        $errors = [];

        $data = [
            'kind' => 'admin_message',
            'ts' => date('c'),
            'sent_by' => (string) ($user['username'] ?? $user['name'] ?? 'admin'),
        ];

        foreach ($tokens as $token) {
            $token = (string) $token;
            if ($token === '') {
                continue;
            }
            try {
                $fcm->sendToToken(
                    deviceToken: $token,
                    title: $title,
                    body: $body,
                    data: $data,
                );
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = substr($e->getMessage(), 0, 200);
                Log::warning('Manual mobile push failed', [
                    'token_prefix' => substr($token, 0, 16),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'success' => $sent > 0,
            'sent' => $sent,
            'failed' => $failed,
            'errors' => array_slice(array_unique($errors), 0, 3),
        ]);
    }

    public function revokeMobileDevice(Request $request, int $id)
    {
        $user = $request->session()->get('auth.user');
        if (($user['role'] ?? '') !== 'admin') {
            return response()->json(['success' => false, 'error' => 'Forbidden'], 403);
        }

        if (!Schema::hasTable('device_tokens')) {
            return response()->json(['success' => false, 'error' => 'device_tokens table not found'], 404);
        }

        $deleted = DB::table('device_tokens')->where('id', $id)->delete();
        if ($deleted === 0) {
            return response()->json(['success' => false, 'error' => 'Device not found'], 404);
        }

        return response()->json(['success' => true]);
    }

    public function logs(Request $request)
    {
        $user = $request->session()->get('auth.user');
        $role = (string) ($user['role'] ?? '');
        if ($role === 'viewer') {
            return response()->json(['success' => true, 'data' => ViewerDummyData::securityLogsText()]);
        }
        if ($role !== 'admin') {
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
