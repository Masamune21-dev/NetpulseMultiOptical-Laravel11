<?php

namespace App\Auth;

use App\Models\PersonalAccessToken;

class NewAccessToken
{
    public function __construct(
        public PersonalAccessToken $accessToken,
        public string $plainTextToken,
    ) {
    }
}

