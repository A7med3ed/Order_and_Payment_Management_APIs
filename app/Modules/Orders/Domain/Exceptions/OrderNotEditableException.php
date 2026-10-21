<?php

declare(strict_types=1);

namespace App\Modules\Orders\Domain\Exceptions;

use App\Modules\Shared\Exceptions\{ApiException, ErrorCode};

final class OrderNotEditableException extends ApiException
{
    public function __construct()
    {
        parent::__construct(ErrorCode::OrderNotEditable, 409, 'Only pending orders are editable.');
    }
}
