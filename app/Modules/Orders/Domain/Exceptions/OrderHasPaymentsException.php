<?php

declare(strict_types=1);

namespace App\Modules\Orders\Domain\Exceptions;

use App\Modules\Shared\Exceptions\{ApiException, ErrorCode};

final class OrderHasPaymentsException extends ApiException
{
    public function __construct(string $message = 'An order with payments cannot be deleted.')
    {
        parent::__construct(ErrorCode::OrderHasPayments, 409, $message);
    }
    public static function cannotCancel(): self
    {
        return new self('An order with a successful or pending payment cannot be cancelled.');
    }
}
