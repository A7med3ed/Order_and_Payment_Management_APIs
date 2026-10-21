<?php

declare(strict_types=1);

namespace App\Modules\Payments\Gateways\DTOs;

final readonly class GatewayChargeRequest
{
    public function __construct(
        public int $paymentId,
        public int $orderId,
        public string $amount,
        public string $currency,
        public string $idempotencyKey,
        #[\SensitiveParameter] public array $details,
    ) {
    }

    public function reference(): string
    {
        return hash('sha256', $this->orderId."\n".$this->idempotencyKey);
    }
}
