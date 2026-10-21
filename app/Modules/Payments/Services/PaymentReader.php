<?php

declare(strict_types=1);

namespace App\Modules\Payments\Services;

use App\Modules\Payments\Contracts\PaymentReaderInterface;
use App\Modules\Payments\DTOs\PaymentData;
use App\Modules\Payments\Models\Payment;

final class PaymentReader implements PaymentReaderInterface
{
    public function findOwned(int $paymentId, int $userId): PaymentData
    {
        return PaymentData::fromModel(Payment::query()->where('user_id', $userId)->findOrFail($paymentId));
    }
}
