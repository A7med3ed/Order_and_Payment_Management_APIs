<?php

declare(strict_types=1);

namespace App\Modules\Payments\Exceptions;

use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Shared\Exceptions\{ApiException, ErrorCode};

final class InvalidPaymentTransitionException extends ApiException
{
    public function __construct(PaymentStatus $from, PaymentStatus $to)
    {
        parent::__construct(ErrorCode::InvalidStatusTransition, 409, "Cannot change payment status from {$from->value} to {$to->value}.");
    }
}
