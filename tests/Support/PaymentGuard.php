<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Orders\Contracts\OrderPaymentGuardInterface;

final readonly class PaymentGuard implements OrderPaymentGuardInterface
{
    public function __construct(private bool $any = false, private bool $successful = false, private bool $pending = false)
    {
    }
    public function hasPayments(int $orderId): bool
    {
        return $this->any;
    }
    public function hasSuccessfulPayment(int $orderId): bool
    {
        return $this->successful;
    }
    public function hasPendingPayment(int $orderId): bool
    {
        return $this->pending;
    }
}
