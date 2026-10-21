<?php

declare(strict_types=1);

namespace App\Modules\Ledger\DTOs;

use App\Modules\Ledger\Enums\{LedgerEntryType, LedgerDirection};
use Carbon\CarbonImmutable;

final readonly class LedgerEntryData
{
    public function __construct(
        public int $orderId,
        public int $userId,
        public ?int $paymentId,
        public LedgerEntryType $type,
        public LedgerDirection $direction,
        public string $amount,
        public string $currency,
        public ?string $gateway = null,
        public ?string $transactionId = null,
        public ?string $idempotencyKey = null,
        public array $metadata = [],
        public ?CarbonImmutable $createdAt = null,
    ) {
    }
}
