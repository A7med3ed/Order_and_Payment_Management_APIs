<?php

declare(strict_types=1);

namespace App\Modules\Orders\DTOs;

final readonly class CreateOrderData
{
    /** @param list<OrderItemData> $items */
    public function __construct(public int $userId, public string $customerName, public string $customerEmail, public array $items)
    {
    }
}
