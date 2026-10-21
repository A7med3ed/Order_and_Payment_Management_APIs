<?php

declare(strict_types=1);

namespace App\Modules\Orders\Domain\Events;

use App\Modules\Orders\Models\Order;

final readonly class OrderCancelled
{
    public function __construct(public Order $order)
    {
    }
}
