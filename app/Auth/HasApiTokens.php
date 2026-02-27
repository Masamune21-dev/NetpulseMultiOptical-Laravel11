<?php

namespace App\Auth;

use App\Models\PersonalAccessToken;
use Illuminate\Support\Str;

trait HasApiTokens
{
    public function createToken(string $name = 'default'): NewAccessToken
    {
        $plain = Str::random(40);

        $token = new PersonalAccessToken();
        $token->forceFill([
            'tokenable_type' => $this->getMorphClass(),
            'tokenable_id' => $this->getKey(),
            'name' => $name,
            'token' => hash('sha256', $plain),
            'abilities' => ['*'],
        ]);
        $token->save();

        // Match Sanctum-style "id|plain" so the middleware can locate by id quickly.
        return new NewAccessToken($token, $token->id . '|' . $plain);
    }

    public function tokens()
    {
        return $this->morphMany(PersonalAccessToken::class, 'tokenable');
    }
}

