<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Enkripsi at-rest untuk kredensial kecil (SNMP community, token bot Telegram).
 *
 * reveal() toleran terhadap nilai lama yang masih plaintext: bila decrypt gagal,
 * nilai apa adanya dikembalikan sehingga transisi poller (cron tiap menit) mulus.
 */
final class Secret
{
    public const PLACEHOLDER = '••••';

    /** Kunci settings yang bersifat rahasia. */
    public const SECRET_SETTINGS = ['bot_token'];

    /**
     * NP-4: redaksi settings untuk respons API.
     * Non-admin: nilai rahasia dikosongkan; admin: placeholder bila terisi.
     * Selalu menyertakan flag <key>_set.
     */
    public static function redactSettings(array $data, bool $isAdmin): array
    {
        foreach (self::SECRET_SETTINGS as $key) {
            $has = isset($data[$key]) && trim((string) $data[$key]) !== '';
            $data[$key . '_set'] = $has;
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $data[$key] = ($has && $isAdmin) ? self::PLACEHOLDER : '';
        }

        return $data;
    }

    /**
     * NP-4: buang placeholder (tidak diubah) dan enkripsi nilai rahasia sebelum disimpan.
     */
    public static function prepareSettingsForSave(array $data): array
    {
        foreach (self::SECRET_SETTINGS as $key) {
            unset($data[$key . '_set']);
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $val = trim((string) $data[$key]);
            if ($val === self::PLACEHOLDER) {
                unset($data[$key]);
                continue;
            }
            $data[$key] = self::encrypt($val);
        }

        return $data;
    }

    public static function encrypt(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        // Idempoten: jangan enkripsi ulang nilai yang sudah terenkripsi.
        if (self::isEncrypted($value)) {
            return $value;
        }

        return Crypt::encryptString($value);
    }

    public static function reveal(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return $value;
        }
    }

    public static function isEncrypted(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }
        try {
            Crypt::decryptString($value);
            return true;
        } catch (DecryptException) {
            return false;
        }
    }
}
