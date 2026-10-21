<?php

declare(strict_types=1);

namespace App\Modules\Orders\DTOs;

final readonly class OrderIdentityData
{
    public function __construct(public int $orderId, public int $userId)
    {
    }
}
