<?php

declare(strict_types=1);

namespace App\Modules\Shared\Idempotency\DTOs;

final readonly class IdempotencyContextData
{
    public function __construct(public int $id, public int $userId, public string $requestHash, public ?StoredResponseData $replay = null)
    {
    }
}
