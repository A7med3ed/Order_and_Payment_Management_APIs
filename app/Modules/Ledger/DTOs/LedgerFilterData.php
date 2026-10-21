<?php

declare(strict_types=1);

namespace App\Modules\Ledger\DTOs;

use App\Modules\Shared\DTOs\PaginationData;

final readonly class LedgerFilterData
{
    public function __construct(public int $orderId, public int $userId, public PaginationData $pagination)
    {
    }
}
