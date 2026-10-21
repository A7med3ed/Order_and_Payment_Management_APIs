<?php

declare(strict_types=1);

namespace App\Modules\Payments\Gateways\DTOs;

use App\Modules\Shared\Support\SensitiveData;

final readonly class GatewayChargeResult
{
    private function __construct(
        public bool $successful,
        public ?string $transactionId,
        public ?string $errorCode,
        public string $message,
        public array $rawResponse,
    ) {
    }

    public static function success(string $transactionId, string $message = 'OK', array $raw = []): self
    {
        return new self(true, $transactionId, null, $message, SensitiveData::sanitize($raw));
    }

    public static function failure(string $errorCode, string $message, array $raw = []): self
    {
        return new self(false, null, $errorCode, $message, SensitiveData::sanitize($raw));
    }
}
