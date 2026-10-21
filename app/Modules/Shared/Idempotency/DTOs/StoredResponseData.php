<?php

declare(strict_types=1);

namespace App\Modules\Shared\Idempotency\DTOs;

final readonly class StoredResponseData
{
    /** @param array<string, string> $headers */
    public function __construct(public int $status, public string $body, public array $headers = [])
    {
    }
}
