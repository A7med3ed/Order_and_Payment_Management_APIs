<?php

declare(strict_types=1);

namespace App\Modules\Payments\DTOs;

use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use Carbon\CarbonImmutable;

final readonly class PaymentData
{
    public function __construct(
        public int $id,
        public int $orderId,
        public int $userId,
        public PaymentStatus $status,
        public string $method,
        public string $amount,
        public string $currency,
        public ?string $transactionId,
        public ?string $failureCode,
        public ?CarbonImmutable $createdAt,
        public ?CarbonImmutable $updatedAt,
    ) {
    }

    public static function fromModel(Payment $payment): self
    {
        return new self(
            (int) $payment->id,
            $payment->order_id,
            $payment->user_id,
            $payment->status,
            $payment->method,
            $payment->amount,
            $payment->currency,
            $payment->transaction_id,
            $payment->failure_code,
            $payment->created_at,
            $payment->updated_at
        );
    }
}
