<?php

declare(strict_types=1);

namespace App\Modules\Shared\Idempotency\DTOs;

final readonly class IdempotencyRequestData
{
    public function __construct(public int $userId, public string $key, public string $requestHash)
    {
    }
}
