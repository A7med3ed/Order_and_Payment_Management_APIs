<?php

declare(strict_types=1);

namespace App\Modules\Payments\Gateways\Implementations;

use App\Modules\Payments\Gateways\Abstract\AbstractPaymentGateway;
use App\Modules\Payments\Gateways\DTOs\{GatewayChargeRequest, GatewayChargeResult};
use RuntimeException;

final class CreditCardGateway extends AbstractPaymentGateway
{
    public function method(): string
    {
        return 'credit_card';
    }

    public function rules(): array
    {
        return [
            'card_number' => ['required', 'string', 'regex:/^[0-9]{13,19}$/D'],
            'expiry_month' => ['required', 'integer', 'between:1,12'],
            'expiry_year' => ['required', 'integer', 'between:2000,9999'],
            'cvv' => ['required', 'string', 'regex:/^[0-9]{3,4}$/D'],
            'card_holder' => ['required', 'string', 'max:255'],
        ];
    }

    protected function performCharge(#[\SensitiveParameter] GatewayChargeRequest $request): GatewayChargeResult
    {
        $last4 = substr($request->details['card_number'], -4);
        $safe = ['last4' => $last4, 'brand' => str_starts_with($request->details['card_number'], '4') ? 'visa' : 'other'];
        return match ($last4) {
            '0002' => GatewayChargeResult::failure('CARD_DECLINED', 'The card was declined.', $safe),
            '0003' => throw new RuntimeException('Simulated provider unavailable.'),
            default => GatewayChargeResult::success('cc_'.$request->reference(), raw: $safe),
        };
    }
}
