<?php

declare(strict_types=1);

namespace App\Modules\Orders\DTOs;

final readonly class UpdateOrderData
{
    /** @param list<OrderItemData>|null $items */
    public function __construct(public int $orderId, public int $userId, public ?string $customerName, public ?string $customerEmail, public ?array $items)
    {
    }
}
