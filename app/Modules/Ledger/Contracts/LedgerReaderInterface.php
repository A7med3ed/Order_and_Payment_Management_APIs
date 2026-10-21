<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Contracts;

use App\Modules\Ledger\DTOs\LedgerFilterData;
use App\Modules\Shared\DTOs\PaginationData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface LedgerReaderInterface
{
    public function forOrder(LedgerFilterData $data): LengthAwarePaginator;
    public function forPayment(int $paymentId, int $userId, PaginationData $pagination): LengthAwarePaginator;
}
