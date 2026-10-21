<?php

declare(strict_types=1);

namespace App\Modules\Orders\DTOs;

final readonly class OrderItemData
{
    public function __construct(public string $productName, public int $quantity, public string $price)
    {
    }
}
