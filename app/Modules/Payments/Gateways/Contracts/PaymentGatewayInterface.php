<?php

declare(strict_types=1);

namespace App\Modules\Payments\Gateways\Contracts;

use App\Modules\Payments\Gateways\DTOs\{GatewayChargeRequest, GatewayChargeResult};

interface PaymentGatewayInterface
{
    public function method(): string;
    /** Rules keyed relative to payment_details. */
    public function rules(): array;
    public function charge(GatewayChargeRequest $request): GatewayChargeResult;
}
