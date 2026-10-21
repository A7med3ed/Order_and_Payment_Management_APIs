<?php

declare(strict_types=1);

namespace App\Modules\Payments\DTOs;

final readonly class PaymentIdentityData
{
    public function __construct(public int $paymentId, public int $userId)
    {
    }
}
