<?php

declare(strict_types=1);

namespace App\Modules\Payments\Gateways\DTOs;

use App\Modules\Shared\Support\SensitiveData;
use InvalidArgumentException;

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
        if ($transactionId === '' || strlen($transactionId) > 255) {
            throw new InvalidArgumentException('A transaction reference of at most 255 bytes is required.');
        }
        return new self(true, $transactionId, null, $message, SensitiveData::sanitize($raw));
    }

    public static function failure(string $errorCode, string $message, array $raw = []): self
    {
        if (preg_match('/^[A-Z][A-Z0-9_]{0,63}$/D', $errorCode) !== 1) {
            throw new InvalidArgumentException('Invalid gateway failure code.');
        }
        return new self(false, null, $errorCode, $message, SensitiveData::sanitize($raw));
    }
}
