<?php

declare(strict_types=1);

namespace App\Modules\Payments\Exceptions;

use App\Modules\Shared\Exceptions\{ApiException, ErrorCode};

final class UnsupportedPaymentMethodException extends ApiException
{
    public function __construct()
    {
        parent::__construct(ErrorCode::ValidationError, 422, 'The payment method is not supported.', ['method' => ['Choose an enabled payment method.']]);
    }
}
