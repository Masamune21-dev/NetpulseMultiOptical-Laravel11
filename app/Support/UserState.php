<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * NP-6: muat ulang role & is_active dari DB (cache 60 detik) supaya perubahan
 * role / penonaktifan akun oleh admin berlaku tanpa menunggu sesi berakhir.
 */
final class UserState
{
    private const TTL = 60;

    /** @return array{role:string,is_active:bool}|null null bila user tidak ada. */
    public static function fresh(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        return Cache::remember(self::key($userId), self::TTL, function () use ($userId) {
            $row = DB::table('users')->select(['role', 'is_active'])->where('id', $userId)->first();
            if (!$row) {
                return null;
            }

            return [
                'role' => (string) $row->role,
                'is_active' => (int) $row->is_active === 1,
            ];
        });
    }

    public static function forget(int $userId): void
    {
        Cache::forget(self::key($userId));
    }

    private static function key(int $userId): string
    {
        return 'np:user_state:' . $userId;
    }
}
