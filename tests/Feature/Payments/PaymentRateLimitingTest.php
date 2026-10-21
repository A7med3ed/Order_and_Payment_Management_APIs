<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Modules\Auth\Models\User;
use App\Modules\Orders\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PaymentRateLimitingTest extends TestCase
{
    use RefreshDatabase;

    private function login(User $user): void
    {
        $this->resetAuthentication();
        $token = $this->app['auth']->guard('api')->login($user);
        $this->resetAuthentication();
        $this->withToken($token);
    }

    public function test_order_limit_is_configurable_separate_per_order_and_resets_after_window(): void
    {
        config(['rate_limits.payments_per_minute' => 100, 'rate_limits.payments_per_order_per_minute' => 2]);
        $user = User::factory()->create();
        $first = Order::factory()->confirmed()->create(['user_id' => $user->id]);
        $second = Order::factory()->confirmed()->create(['user_id' => $user->id]);
        $this->login($user);
        // Missing keys still count; the limiter must precede idempotency.
        $url = "/api/orders/{$first->id}/payments";
        $this->postJson($url, [])->assertUnprocessable();
        $this->postJson($url, [])->assertUnprocessable();
        $this->postJson($url, [])->assertStatus(429)->assertJsonPath('code', 'TOO_MANY_REQUESTS')
            ->assertHeader('Retry-After')->assertHeader('X-RateLimit-Limit', '2')->assertHeader('X-RateLimit-Remaining', '0');
        $this->postJson("/api/orders/{$second->id}/payments", [])->assertUnprocessable();
        $this->travel(61)->seconds();
        $this->postJson($url, [])->assertUnprocessable()->assertJsonPath('code', 'IDEMPOTENCY_KEY_REQUIRED');
    }

    public function test_user_limit_spans_orders_but_other_user_is_unaffected(): void
    {
        config(['rate_limits.payments_per_minute' => 2, 'rate_limits.payments_per_order_per_minute' => 100]);
        $first = User::factory()->create();
        $second = User::factory()->create();
        $one = Order::factory()->confirmed()->create(['user_id' => $first->id]);
        $two = Order::factory()->confirmed()->create(['user_id' => $first->id]);
        $other = Order::factory()->confirmed()->create(['user_id' => $second->id]);
        $this->login($first);
        $this->postJson("/api/orders/{$one->id}/payments", [])->assertUnprocessable();
        $this->postJson("/api/orders/{$two->id}/payments", [])->assertUnprocessable();
        $this->postJson("/api/orders/{$two->id}/payments", [])->assertStatus(429)->assertHeader('X-RateLimit-Limit', '2');
        $this->login($second);
        $this->postJson("/api/orders/{$other->id}/payments", [])->assertUnprocessable();
    }

    public function test_cached_replays_count_toward_payment_limit(): void
    {
        config(['rate_limits.payments_per_minute' => 100, 'rate_limits.payments_per_order_per_minute' => 2]);
        $user = User::factory()->create();
        $order = Order::factory()->confirmed()->create(['user_id' => $user->id]);
        $this->login($user);
        $url = "/api/orders/{$order->id}/payments";
        $payload = ['method' => 'paypal', 'payment_details' => ['email' => 'payer@example.com']];
        $headers = ['Idempotency-Key' => 'rate-replay-01'];
        $this->postJson($url, $payload, $headers)->assertCreated();
        $this->postJson($url, $payload, $headers)->assertCreated()->assertHeader('Idempotent-Replayed', 'true');
        $this->postJson($url, $payload, $headers)->assertStatus(429);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('ledger_entries', 2);
    }
}
