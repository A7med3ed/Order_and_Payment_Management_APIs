<?php

declare(strict_types=1);

namespace App\Modules\Orders\Domain\Exceptions;

use App\Modules\Shared\Exceptions\{ApiException, ErrorCode};

final class EmptyOrderException extends ApiException
{
    public function __construct()
    {
        parent::__construct(ErrorCode::ValidationError, 422, 'An order must have at least one item.');
    }
}
