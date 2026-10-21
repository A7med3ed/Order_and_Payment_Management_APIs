<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Contracts;

use App\Modules\Ledger\DTOs\LedgerEntryData;

interface LedgerRecorderInterface
{
    public function record(LedgerEntryData $data): void;
}
