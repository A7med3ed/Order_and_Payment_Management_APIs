<?php

declare(strict_types=1);

namespace App\Modules\Payments\Exceptions;

use App\Modules\Shared\Exceptions\{ApiException, ErrorCode};

final class OrderAlreadyPaidException extends ApiException
{
    public function __construct()
    {
        parent::__construct(ErrorCode::OrderAlreadyPaid, 409, 'This order already has a successful payment.');
    }
}
