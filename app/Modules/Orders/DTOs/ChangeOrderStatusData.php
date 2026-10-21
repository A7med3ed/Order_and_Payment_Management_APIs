<?php

declare(strict_types=1);

namespace App\Modules\Orders\DTOs;

use App\Modules\Orders\Domain\Enums\OrderStatus;

final readonly class ChangeOrderStatusData
{
    public function __construct(public int $orderId, public int $userId, public OrderStatus $targetStatus)
    {
    }
}
