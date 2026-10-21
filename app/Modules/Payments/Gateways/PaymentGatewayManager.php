<?php

declare(strict_types=1);

namespace App\Modules\Payments\Gateways;

use App\Modules\Payments\Exceptions\UnsupportedPaymentMethodException;
use App\Modules\Payments\Gateways\Contracts\PaymentGatewayInterface;
use App\Modules\Payments\Gateways\DTOs\GatewayConfigData;
use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use LogicException;

final class PaymentGatewayManager
{
    private array $extensions = [];

    public function __construct(private readonly Container $container, private readonly Repository $config)
    {
    }

    public function extend(string $method, string|Closure $gateway): void
    {
        if (preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $method) !== 1) {
            throw new LogicException('Gateway names must be lowercase identifiers of at most 64 characters.');
        }
        $this->extensions[$method] = $gateway;
    }

    public function supportedMethods(): array
    {
        $enabled = array_keys(array_filter($this->config->get('payment.gateways', []), fn (array $gateway): bool => (bool) ($gateway['enabled'] ?? false)));
        return array_values(array_unique([...$enabled, ...array_keys($this->extensions)]));
    }

    public function resolve(string $method): PaymentGatewayInterface
    {
        if (! in_array($method, $this->supportedMethods(), true)) {
            throw new UnsupportedPaymentMethodException();
        }
        $settings = $this->config->get('payment.gateways.'.$method, []);
        $data = new GatewayConfigData($method, true, $settings['credentials'] ?? [], $settings['options'] ?? []);
        $driver = $this->extensions[$method] ?? $settings['driver'];
        $gateway = $driver instanceof Closure
            ? $driver($this->container, $data)
            : $this->container->make($driver, ['config' => $data]);
        if (! $gateway instanceof PaymentGatewayInterface || $gateway->method() !== $method) {
            throw new LogicException('Gateway driver must implement its registered method.');
        }
        return $gateway;
    }
}
