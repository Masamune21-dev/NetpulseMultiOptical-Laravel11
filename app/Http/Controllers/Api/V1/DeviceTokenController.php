<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
            'platform' => ['nullable', 'string', 'max:50'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = $request->user();

        $model = DeviceToken::query()->firstOrNew(['token' => $data['token']]);
        $model->fill([
            'user_id' => $user->id,
            'platform' => $data['platform'] ?? $model->platform,
            'device_name' => $data['device_name'] ?? $model->device_name,
            'last_seen_at' => now(),
        ]);
        $model->save();

        return response()->json(['success' => true]);
    }
}

