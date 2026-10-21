<?php

declare(strict_types=1);

namespace App\Modules\Orders\Contracts;

interface OrderPaymentGuardInterface
{
    public function hasPayments(int $orderId): bool;
    public function hasSuccessfulPayment(int $orderId): bool;
    public function hasPendingPayment(int $orderId): bool;
}
