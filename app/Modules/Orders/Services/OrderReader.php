<?php

declare(strict_types=1);

namespace App\Modules\Orders\Services;

use App\Modules\Orders\Contracts\OrderReaderInterface;
use App\Modules\Orders\DTOs\OrderSnapshotData;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\DB;
use LogicException;

final class OrderReader implements OrderReaderInterface
{
    public function findOwned(int $orderId, int $userId): OrderSnapshotData
    {
        return $this->snapshot(Order::query()->where('user_id', $userId)->findOrFail($orderId));
    }

    public function lockForPayment(int $orderId, int $userId): OrderSnapshotData
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('An active transaction is required to lock an order.');
        }
        return $this->snapshot(Order::query()->where('user_id', $userId)->lockForUpdate()->findOrFail($orderId));
    }

    private function snapshot(Order $order): OrderSnapshotData
    {
        return new OrderSnapshotData((int) $order->id, $order->user_id, $order->total_amount, $order->canBePaid());
    }
}
