<?php

declare(strict_types=1);

namespace App\Modules\Auth\DTOs;

final readonly class RegisterData
{
    public function __construct(public string $name, public string $email, public string $password)
    {
    }
}
