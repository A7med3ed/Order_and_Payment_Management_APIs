<?php

declare(strict_types=1);

namespace App\Modules\Payments\Services;

use App\Modules\Orders\Contracts\OrderPaymentGuardInterface;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Enums\PaymentStatus;

final class OrderPaymentGuard implements OrderPaymentGuardInterface
{
    public function hasPayments(int $orderId): bool
    {
        return Payment::query()->where('order_id', $orderId)->exists();
    }
    public function hasSuccessfulPayment(int $orderId): bool
    {
        return Payment::query()->where('order_id', $orderId)->where('status', PaymentStatus::Successful)->exists();
    }
    public function hasPendingPayment(int $orderId): bool
    {
        return Payment::query()->where('order_id', $orderId)->where('status', PaymentStatus::Pending)->exists();
    }
}
