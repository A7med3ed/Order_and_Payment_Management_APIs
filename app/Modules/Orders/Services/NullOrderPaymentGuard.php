<?php

declare(strict_types=1);

namespace App\Modules\Orders\Services;

use App\Modules\Orders\Contracts\OrderPaymentGuardInterface;

final class NullOrderPaymentGuard implements OrderPaymentGuardInterface
{
    public function hasPayments(int $orderId): bool
    {
        return false;
    }
    public function hasSuccessfulPayment(int $orderId): bool
    {
        return false;
    }
    public function hasPendingPayment(int $orderId): bool
    {
        return false;
    }
}
