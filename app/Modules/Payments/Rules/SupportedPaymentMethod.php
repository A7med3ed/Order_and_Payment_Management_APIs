<?php

declare(strict_types=1);

namespace App\Modules\Payments\Rules;

use App\Modules\Payments\Gateways\PaymentGatewayManager;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class SupportedPaymentMethod implements ValidationRule
{
    public function __construct(private PaymentGatewayManager $gateways)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! in_array($value, $this->gateways->supportedMethods(), true)) {
            $fail('Choose an enabled payment method.');
        }
    }
}
