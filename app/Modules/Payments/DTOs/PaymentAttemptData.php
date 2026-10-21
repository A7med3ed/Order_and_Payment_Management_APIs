<?php

declare(strict_types=1);

namespace App\Modules\Payments\DTOs;

use App\Modules\Payments\Models\Payment;

final readonly class PaymentAttemptData
{
    public function __construct(public Payment $payment, public bool $created)
    {
    }
}
