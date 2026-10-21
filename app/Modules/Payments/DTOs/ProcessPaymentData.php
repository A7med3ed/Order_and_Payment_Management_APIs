<?php

declare(strict_types=1);

namespace App\Modules\Payments\DTOs;

use App\Modules\Shared\Support\CanonicalJson;

final readonly class ProcessPaymentData
{
    public function __construct(
        public int $orderId,
        public int $userId,
        public string $method,
        #[\SensitiveParameter] public array $paymentDetails,
        public string $idempotencyKey,
    ) {
    }

    public function fingerprint(): string
    {
        return hash('sha256', CanonicalJson::encode([
            'order_id' => $this->orderId, 'user_id' => $this->userId,
            'method' => $this->method, 'details' => $this->paymentDetails,
        ]));
    }
}
