<?php

declare(strict_types=1);

namespace App\Modules\Payments\Gateways\Implementations;

use App\Modules\Payments\Gateways\Abstract\AbstractPaymentGateway;
use App\Modules\Payments\Gateways\DTOs\{GatewayChargeRequest, GatewayChargeResult};
use RuntimeException;

final class PayPalGateway extends AbstractPaymentGateway
{
    public function method(): string
    {
        return 'paypal';
    }

    public function rules(): array
    {
        return ['email' => ['required', 'string', 'email', 'max:255']];
    }

    protected function performCharge(#[\SensitiveParameter] GatewayChargeRequest $request): GatewayChargeResult
    {
        $email = strtolower($request->details['email']);
        return match (true) {
            str_starts_with($email, 'fail@') => GatewayChargeResult::failure('INSUFFICIENT_FUNDS', 'Insufficient PayPal funds.'),
            str_starts_with($email, 'error@') => throw new RuntimeException('Simulated provider unavailable.'),
            default => GatewayChargeResult::success('pp_'.$request->reference()),
        };
    }
}
