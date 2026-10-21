<?php

declare(strict_types=1);

namespace App\Modules\Orders\Contracts;

use App\Modules\Orders\DTOs\OrderSnapshotData;

interface OrderReaderInterface
{
    public function findOwned(int $orderId, int $userId): OrderSnapshotData;
    public function lockForPayment(int $orderId, int $userId): OrderSnapshotData;
}
