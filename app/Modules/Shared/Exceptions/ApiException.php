<?php

declare(strict_types=1);

namespace App\Modules\Shared\Exceptions;

use RuntimeException;
use Throwable;

class ApiException extends RuntimeException
{
    /** @param array<string, list<string>> $errors */
    public function __construct(
        public readonly ErrorCode $errorCode,
        public readonly int $httpStatus,
        string $message,
        public readonly array $errors = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
