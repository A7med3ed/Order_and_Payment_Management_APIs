<?php

declare(strict_types=1);

namespace App\Modules\Auth\DTOs;

use App\Modules\Auth\Models\User;

final readonly class TokenData
{
    public function __construct(
        public string $accessToken,
        public int $expiresIn,
        public User $user,
        public string $tokenType = 'bearer',
    ) {
    }
}
