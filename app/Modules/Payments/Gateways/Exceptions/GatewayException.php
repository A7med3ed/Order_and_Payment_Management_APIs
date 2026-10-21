<?php

declare(strict_types=1);

namespace App\Modules\Payments\Gateways\Exceptions;

use RuntimeException;

final class GatewayException extends RuntimeException
{
    public function __construct(public readonly string $method)
    {
        // Deliberately omit provider messages and exception chains: either may contain credentials.
        parent::__construct('The payment provider could not complete the request.');
    }
}
