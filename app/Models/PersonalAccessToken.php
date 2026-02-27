<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PersonalAccessToken extends Model
{
    protected $table = 'personal_access_tokens';

    protected $fillable = [
        'tokenable_type',
        'tokenable_id',
        'name',
        'token',
        'abilities',
        'last_used_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function tokenable(): MorphTo
    {
        return $this->morphTo();
    }

    public static function findToken(string $token): ?self
    {
        $id = null;
        $plain = $token;

        // Accept "id|plain" (Sanctum style).
        if (str_contains($token, '|')) {
            [$maybeId, $maybePlain] = explode('|', $token, 2);
            if (ctype_digit($maybeId)) {
                $id = (int) $maybeId;
                $plain = $maybePlain;
            }
        }

        $hash = hash('sha256', $plain);

        $model = null;
        if ($id !== null) {
            $model = self::query()->find($id);
            if (!$model) {
                return null;
            }

            return hash_equals((string) $model->token, $hash) ? $model : null;
        }

        return self::query()->where('token', $hash)->first();
    }
}

