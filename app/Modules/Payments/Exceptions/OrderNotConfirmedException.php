<?php

declare(strict_types=1);

namespace App\Modules\Payments\Exceptions;

use App\Modules\Shared\Exceptions\{ApiException, ErrorCode};

final class OrderNotConfirmedException extends ApiException
{
    public function __construct()
    {
        parent::__construct(ErrorCode::OrderNotConfirmed, 409, 'Only confirmed orders can be paid.');
    }
}
