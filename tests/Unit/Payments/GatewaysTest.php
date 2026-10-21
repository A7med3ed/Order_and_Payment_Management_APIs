<?php

declare(strict_types=1);

namespace Tests\Unit\Payments;

use App\Modules\Payments\Gateways\DTOs\{GatewayChargeRequest, GatewayChargeResult, GatewayConfigData};
use App\Modules\Payments\Gateways\Implementations\{CreditCardGateway, PayPalGateway};
use App\Modules\Payments\Gateways\Exceptions\GatewayException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GatewaysTest extends TestCase
{
    private function request(array $details, string $key = 'gateway-test-key'): GatewayChargeRequest
    {
        return new GatewayChargeRequest(1, 1, '39.98', 'USD', $key, $details);
    }

    public function test_card_success_is_deterministic_and_keeps_only_last4_and_brand(): void
    {
        $gateway = new CreditCardGateway(new GatewayConfigData('credit_card', true));
        $request = $this->request(['card_number' => '4242424242424242', 'cvv' => '123']);
        $result = $gateway->charge($request);
        $this->assertTrue($result->successful);
        $this->assertSame(['last4' => '4242', 'brand' => 'visa'], $result->rawResponse);
        $this->assertSame($result->transactionId, $gateway->charge($request)->transactionId);
        $this->assertNotSame($result->transactionId, $gateway->charge($this->request($request->details, 'different-key'))->transactionId);
        $this->assertNotSame($result->transactionId, $gateway->charge(new GatewayChargeRequest(2, 2, '39.98', 'USD', $request->idempotencyKey, $request->details))->transactionId);
    }

    public function test_card_decline_is_a_failed_result(): void
    {
        $result = (new CreditCardGateway(new GatewayConfigData('credit_card', true)))->charge($this->request(['card_number' => '4000000000000002']));
        $this->assertFalse($result->successful);
        $this->assertSame('CARD_DECLINED', $result->errorCode);
        $this->assertNull($result->transactionId);
        $this->assertSame(['last4' => '0002', 'brand' => 'visa'], $result->rawResponse);
    }

    public function test_paypal_success_and_decline_are_deterministic(): void
    {
        $gateway = new PayPalGateway(new GatewayConfigData('paypal', true));
        $request = $this->request(['email' => 'payer@example.com']);
        $success = $gateway->charge($request);
        $this->assertTrue($success->successful);
        $this->assertSame($success->transactionId, $gateway->charge($request)->transactionId);
        $this->assertSame([], $success->rawResponse);
        $failure = $gateway->charge($this->request(['email' => 'FAIL@example.com']));
        $this->assertSame('INSUFFICIENT_FUNDS', $failure->errorCode);
        $this->assertFalse($failure->successful);
    }

    public static function providerErrors(): array
    {
        return [[CreditCardGateway::class, 'credit_card', ['card_number' => '4000000000000003']],
            [PayPalGateway::class, 'paypal', ['email' => 'error@example.com']]];
    }

    #[DataProvider('providerErrors')]
    public function test_provider_errors_are_normalized_without_sensitive_exception_chains(string $class, string $method, array $details): void
    {
        try {
            (new $class(new GatewayConfigData($method, true)))->charge($this->request($details));
            $this->fail('Expected GatewayException.');
        } catch (GatewayException $exception) {
            $this->assertSame($method, $exception->method);
            $this->assertSame('The payment provider could not complete the request.', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }
    }

    public function test_result_sanitizes_nested_credentials_and_card_data(): void
    {
        $result = GatewayChargeResult::failure('DECLINED', 'Declined.', ['card_number' => '4242424242424242', 'cvv' => '123', 'nested' => ['clientSecret' => 'secret', 'access_token' => 'token', 'safe' => true]]);
        $this->assertSame(['nested' => ['safe' => true]], $result->rawResponse);
    }
}
