<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Modules\Payments\Exceptions\UnsupportedPaymentMethodException;
use App\Modules\Payments\Gateways\PaymentGatewayManager;
use App\Modules\Payments\Gateways\DTOs\GatewayConfigData;
use App\Modules\Payments\Gateways\Implementations\{CreditCardGateway, PayPalGateway};
use Tests\Support\ExampleGateway;
use Tests\TestCase;

final class GatewayManagerTest extends TestCase
{
    public function test_manager_resolves_configured_enabled_drivers(): void
    {
        $manager = app(PaymentGatewayManager::class);
        $this->assertSame($manager, app(PaymentGatewayManager::class));
        $this->assertSame(['credit_card', 'paypal'], $manager->supportedMethods());
        $this->assertInstanceOf(CreditCardGateway::class, $manager->resolve('credit_card'));
        $this->assertInstanceOf(PayPalGateway::class, $manager->resolve('paypal'));
    }

    public function test_unknown_method_is_rejected(): void
    {
        $this->expectException(UnsupportedPaymentMethodException::class);
        app(PaymentGatewayManager::class)->resolve('unknown');
    }

    public function test_disabled_method_cannot_be_resolved_even_after_an_earlier_resolution(): void
    {
        $manager = app(PaymentGatewayManager::class);
        $manager->resolve('paypal');
        config(['payment.gateways.paypal.enabled' => false]);
        $this->assertSame(['credit_card'], $manager->supportedMethods());
        $this->expectException(UnsupportedPaymentMethodException::class);
        $manager->resolve('paypal');
    }

    public function test_extend_supports_both_class_and_closure_with_injected_configuration(): void
    {
        $manager = app(PaymentGatewayManager::class);
        $manager->extend('example', ExampleGateway::class);
        $this->assertInstanceOf(ExampleGateway::class, $manager->resolve('example'));
        $manager->extend('example', function ($container, GatewayConfigData $config): ExampleGateway {
            $this->assertSame('example', $config->method);
            return $container->make(ExampleGateway::class, ['config' => $config]);
        });
        $this->assertContains('example', $manager->supportedMethods());
        $this->assertSame('example', $manager->resolve('example')->method());
    }

    public function test_driver_must_match_registered_method(): void
    {
        app(PaymentGatewayManager::class)->extend('wrong', ExampleGateway::class);
        $this->expectException(\LogicException::class);
        app(PaymentGatewayManager::class)->resolve('wrong');
    }
}
