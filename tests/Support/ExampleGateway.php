<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Payments\Gateways\Abstract\AbstractPaymentGateway;
use App\Modules\Payments\Gateways\DTOs\{GatewayChargeRequest, GatewayChargeResult};

final class ExampleGateway extends AbstractPaymentGateway
{
    public function method(): string
    {
        return 'example';
    }

    public function rules(): array
    {
        return ['reference' => ['required', 'string', 'max:100']];
    }

    protected function performCharge(GatewayChargeRequest $request): GatewayChargeResult
    {
        return GatewayChargeResult::success('example_'.$request->reference());
    }
}
