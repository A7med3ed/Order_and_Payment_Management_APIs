<?php

declare(strict_types=1);

namespace App\Modules\Payments\Events;

use App\Modules\Payments\Models\Payment;

final readonly class PaymentSucceeded
{
    public function __construct(public Payment $payment)
    {
    }
}
