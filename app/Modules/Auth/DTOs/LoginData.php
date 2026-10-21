<?php

declare(strict_types=1);

namespace App\Modules\Auth\DTOs;

final readonly class LoginData
{
    public function __construct(public string $email, public string $password)
    {
    }
}
