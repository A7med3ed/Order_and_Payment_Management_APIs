<?php

declare(strict_types=1);

namespace App\Modules\Payments\Contracts;

use App\Modules\Payments\DTOs\PaymentData;

interface PaymentReaderInterface
{
    public function findOwned(int $paymentId, int $userId): PaymentData;
}
