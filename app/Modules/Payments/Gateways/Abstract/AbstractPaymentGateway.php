<?php

declare(strict_types=1);

namespace App\Modules\Payments\Gateways\Abstract;

use App\Modules\Payments\Gateways\Contracts\PaymentGatewayInterface;
use App\Modules\Payments\Gateways\DTOs\{GatewayChargeRequest, GatewayChargeResult, GatewayConfigData};
use App\Modules\Payments\Gateways\Exceptions\GatewayException;
use Throwable;

abstract class AbstractPaymentGateway implements PaymentGatewayInterface
{
    public function __construct(protected readonly GatewayConfigData $config)
    {
    }

    final public function charge(#[\SensitiveParameter] GatewayChargeRequest $request): GatewayChargeResult
    {
        try {
            return $this->performCharge($request);
        } catch (Throwable) {
            throw new GatewayException($this->method());
        }
    }

    abstract protected function performCharge(GatewayChargeRequest $request): GatewayChargeResult;
}
