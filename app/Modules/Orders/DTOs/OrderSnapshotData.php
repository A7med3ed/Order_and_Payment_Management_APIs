<?php

declare(strict_types=1);

namespace App\Modules\Orders\DTOs;

final readonly class OrderSnapshotData
{
    public function __construct(public int $orderId, public int $userId, public string $totalAmount, public bool $payable)
    {
    }
}
