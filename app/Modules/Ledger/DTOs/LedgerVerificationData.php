<?php

declare(strict_types=1);

namespace App\Modules\Ledger\DTOs;

final readonly class LedgerVerificationData
{
    public function __construct(public bool $valid, public int $checked, public ?string $error = null)
    {
    }
}
