<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Services\FcmService;
use Illuminate\Http\Request;

class PushTestController extends Controller
{
    public function __construct(private readonly FcmService $fcm)
    {
    }

    public function send(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:300'],
        ]);

        // Hanya ke perangkat milik pemanggil sendiri. Dulu parameter `token` dari body
        // dipakai apa adanya, sehingga pengguna mana pun (termasuk viewer) bisa mengirim
        // notifikasi berjudul & berisi bebas ke HP orang lain atas nama Netpulse.
        // Aplikasi mobile tidak pernah mengirim `token`; bila dikirim, diabaikan.
        $token = (string) (DeviceToken::query()
            ->where('user_id', $user->id)
            ->orderByDesc('last_seen_at')
            ->orderByDesc('id')
            ->value('token') ?? '');

        if ($token === '') {
            return response()->json(['error' => 'No device token found for user'], 400);
        }

        $title = (string) ($data['title'] ?? 'Netpulse Test');
        $body = (string) ($data['body'] ?? ('Hello ' . ($user->full_name ?? $user->username ?? 'user') . '!'));

        $resp = $this->fcm->sendToToken(
            deviceToken: $token,
            title: $title,
            body: $body,
            data: [
                'kind' => 'test',
                'ts' => now()->toIso8601String(),
            ],
        );

        return response()->json(['success' => true, 'fcm' => $resp]);
    }
}

