<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Modules\Auth\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\DTOs\ProcessPaymentData;
use App\Modules\Payments\DTOs\PaymentFilterData;
use App\Modules\Shared\DTOs\PaginationData;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Gateways\PaymentGatewayManager;
use App\Modules\Payments\Gateways\Contracts\PaymentGatewayInterface;
use App\Modules\Payments\Gateways\DTOs\GatewayChargeResult;
use App\Modules\Payments\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Support\ExampleGateway;
use Tests\TestCase;

final class PaymentHardeningTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Order $order;
    private array $payload = ['method' => 'paypal', 'payment_details' => ['email' => 'payer@example.com']];

    protected function setUp(): void
    {
        parent::setUp();
        config(['rate_limits.payments_per_minute' => 100, 'rate_limits.payments_per_order_per_minute' => 100]);
        $this->owner = User::factory()->create();
        $token = $this->app['auth']->guard('api')->login($this->owner);
        $this->resetAuthentication();
        $this->withToken($token);
        $this->order = Order::factory()->confirmed()->create(['user_id' => $this->owner->id]);
    }

    public function test_replay_calls_gateway_once_even_after_cache_expiry_and_rejects_different_payload(): void
    {
        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('method')->andReturn('paypal');
        $gateway->shouldReceive('rules')->andReturn(['email' => ['required', 'email']]);
        $gateway->shouldReceive('charge')->once()->andReturn(GatewayChargeResult::success('spy-payment-001'));
        app(PaymentGatewayManager::class)->extend('paypal', fn () => $gateway);
        $url = "/api/orders/{$this->order->id}/payments";
        $headers = ['Idempotency-Key' => 'persistent-replay-01'];
        $first = $this->postJson($url, $this->payload, $headers)->assertCreated();
        $replay = $this->postJson($url, $this->payload, $headers)->assertCreated()->assertHeader('Idempotent-Replayed', 'true');
        $this->assertSame($first->getContent(), $replay->getContent());
        $this->travel(25)->hours();
        // Authenticate again: the old JWT has expired along with the cache entry.
        $this->resetAuthentication();
        $token = $this->app['auth']->guard('api')->login($this->owner);
        $this->resetAuthentication();
        $this->withToken($token);
        $again = $this->postJson($url, $this->payload, $headers)->assertCreated();
        $this->assertSame($first->getContent(), $again->getContent());
        DB::table('idempotency_keys')->delete();
        $this->postJson($url, ['method' => 'paypal', 'payment_details' => ['email' => 'other@example.com']], $headers)
            ->assertUnprocessable()->assertJsonPath('code', 'IDEMPOTENCY_KEY_REUSED');
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('ledger_entries', 2);
    }

    public function test_pending_same_key_is_returned_without_charge_and_other_keys_and_cancellation_are_blocked(): void
    {
        $payment = Payment::factory()->create(['order_id' => $this->order->id, 'user_id' => $this->owner->id,
            'idempotency_key' => 'pending-attempt-01', 'details' => $this->payload['payment_details']]);
        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('method')->andReturn('paypal');
        $gateway->shouldReceive('rules')->andReturn(['email' => ['required', 'email']]);
        $gateway->shouldNotReceive('charge');
        app(PaymentGatewayManager::class)->extend('paypal', fn () => $gateway);
        $this->postJson("/api/orders/{$this->order->id}/payments", $this->payload, ['Idempotency-Key' => 'pending-attempt-01'])
            ->assertCreated()->assertJsonPath('data.id', $payment->id)->assertJsonPath('data.status', 'pending');
        $this->postJson("/api/orders/{$this->order->id}/payments", $this->payload, ['Idempotency-Key' => 'pending-attempt-02'])
            ->assertStatus(409)->assertJsonPath('code', 'PAYMENT_IN_PROGRESS')->assertHeader('Retry-After', '1');
        $this->patchJson("/api/orders/{$this->order->id}/status", ['status' => 'cancelled'])
            ->assertStatus(409)->assertJsonPath('code', 'ORDER_HAS_PAYMENTS');
        $this->deleteJson("/api/orders/{$this->order->id}")->assertStatus(409);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_third_gateway_works_through_http_with_no_production_changes(): void
    {
        app(PaymentGatewayManager::class)->extend('example', ExampleGateway::class);
        $url = "/api/orders/{$this->order->id}/payments";
        $headers = ['Idempotency-Key' => 'third-gateway-01'];
        $this->postJson($url, ['method' => 'example', 'payment_details' => ['reference' => '']], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('payment_details.reference');
        $this->postJson($url, ['method' => 'example', 'payment_details' => ['reference' => 'example-reference']], $headers)
            ->assertCreated()->assertJsonPath('data.method', 'example')->assertJsonPath('data.status', 'successful');
        $this->assertDatabaseCount('ledger_entries', 2);
    }

    public function test_config_registered_gateway_also_works_through_http(): void
    {
        config(['payment.gateways.example' => ['driver' => ExampleGateway::class, 'enabled' => true, 'credentials' => ['secret' => 'test-only']]]);
        $this->postJson("/api/orders/{$this->order->id}/payments", ['method' => 'example', 'payment_details' => ['reference' => 'configured-reference']], ['Idempotency-Key' => 'config-gateway-01'])
            ->assertCreated()->assertJsonPath('data.method', 'example');
    }

    public function test_disabled_gateway_returns_validation_error_without_writes(): void
    {
        config(['payment.gateways.paypal.enabled' => false]);
        $this->postJson("/api/orders/{$this->order->id}/payments", $this->payload, ['Idempotency-Key' => 'disabled-gateway-01'])
            ->assertUnprocessable()->assertJsonValidationErrors('method');
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('ledger_entries', 0);
    }

    public function test_service_level_same_key_retries_return_existing_payment_without_extra_charge(): void
    {
        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('method')->andReturn('paypal');
        $gateway->shouldReceive('charge')->once()->andReturn(GatewayChargeResult::success('service-replay-001'));
        app(PaymentGatewayManager::class)->extend('paypal', fn () => $gateway);
        $data = new ProcessPaymentData($this->order->id, $this->owner->id, 'paypal', $this->payload['payment_details'], 'service-retry-01');
        $first = app(PaymentService::class)->process($data);
        $second = app(PaymentService::class)->process($data);
        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('ledger_entries', 2);
    }

    public function test_authentication_precedes_idempotency_and_payment_throttle(): void
    {
        $this->resetAuthentication();
        $this->withoutToken();
        $this->postJson("/api/orders/{$this->order->id}/payments", $this->payload)->assertUnauthorized()->assertJsonPath('code', 'UNAUTHENTICATED');
        $this->assertDatabaseCount('idempotency_keys', 0);
    }

    public function test_payment_pagination_keeps_filters_and_queries_do_not_grow_with_page_size(): void
    {
        $orders = Order::factory()->confirmed()->count(4)->create(['user_id' => $this->owner->id]);
        foreach ($orders as $order) {
            Payment::factory()->failed()->create(['order_id' => $order->id, 'user_id' => $this->owner->id]);
        }
        $response = $this->getJson('/api/payments?status=failed&method=paypal&per_page=1')->assertOk()->assertJsonPath('meta.total', 4);
        $next = $response->json('links.next');
        parse_str(parse_url($next, PHP_URL_QUERY), $query);
        $this->assertSame('failed', $query['status']);
        $this->assertSame('paypal', $query['method']);
        $this->assertSame('2', $query['page']);
        $this->getJson($next)->assertOk()->assertJsonPath('meta.total', 4)->assertJsonPath('meta.current_page', 2);

        DB::enableQueryLog();
        try {
            DB::flushQueryLog();
            app(PaymentService::class)->list(new PaymentFilterData($this->owner->id, new PaginationData(1, 1)));
            $one = count(DB::getQueryLog());
            DB::flushQueryLog();
            app(PaymentService::class)->list(new PaymentFilterData($this->owner->id, new PaginationData(1, 100)));
            $many = count(DB::getQueryLog());
            $this->assertSame($one, $many, 'Listing payments should not add a query per row.');
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }
}
