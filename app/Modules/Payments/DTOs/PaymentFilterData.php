<?php

declare(strict_types=1);

namespace App\Modules\Payments\DTOs;

use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Shared\DTOs\PaginationData;

final readonly class PaymentFilterData
{
    public function __construct(
        public int $userId,
        public PaginationData $pagination,
        public ?int $orderId = null,
        public ?PaymentStatus $status = null,
        public ?string $method = null,
    ) {
    }
}
