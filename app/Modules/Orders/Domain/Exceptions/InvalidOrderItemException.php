<?php

declare(strict_types=1);

namespace App\Modules\Orders\Domain\Exceptions;

use App\Modules\Shared\Exceptions\{ApiException, ErrorCode};
use Throwable;

final class InvalidOrderItemException extends ApiException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct(ErrorCode::ValidationError, 422, $message, ['items' => [$message]], $previous);
    }
}
