<?php

declare(strict_types=1);

namespace App\Modules\Ledger\DTOs;

use App\Modules\Shared\DTOs\PaginationData;

final readonly class PaymentLedgerData
{
    public function __construct(public int $paymentId, public int $userId, public PaginationData $pagination)
    {
    }
}
