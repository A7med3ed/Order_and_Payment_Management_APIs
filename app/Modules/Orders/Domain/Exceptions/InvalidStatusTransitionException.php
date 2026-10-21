<?php

declare(strict_types=1);

namespace App\Modules\Orders\Domain\Exceptions;

use App\Modules\Orders\Domain\Enums\OrderStatus;
use App\Modules\Shared\Exceptions\{ApiException, ErrorCode};

final class InvalidStatusTransitionException extends ApiException
{
    public function __construct(OrderStatus $from, OrderStatus $to)
    {
        parent::__construct(ErrorCode::InvalidStatusTransition, 409, "Cannot change order status from {$from->value} to {$to->value}.");
    }
}
