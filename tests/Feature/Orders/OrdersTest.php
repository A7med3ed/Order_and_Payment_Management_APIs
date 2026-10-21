<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use App\Modules\Auth\Models\User;
use App\Modules\Orders\Contracts\{OrderPaymentGuardInterface, OrderReaderInterface};
use App\Modules\Orders\Domain\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\PaymentGuard;
use Tests\TestCase;

final class OrdersTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $token = $this->app['auth']->guard('api')->login($this->owner);
        $this->resetAuthentication();
        $this->withToken($token);
    }
    private function payload(): array
    {
        return ['customer_name' => 'Customer', 'customer_email' => 'customer@example.com',
            'items' => [['product_name' => 'First', 'quantity' => 3, 'price' => '19.99']]];
    }

    public function test_create_show_update_and_delete_with_exact_totals_and_ignored_client_fields(): void
    {
        $created = $this->postJson('/api/orders', [...$this->payload(), 'total_amount' => '0.01', 'status' => 'confirmed', 'user_id' => 999])
            ->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.total_amount', '59.97')
            ->assertJsonPath('data.user_id', $this->owner->id);
        $id = $created->json('data.id');
        $this->getJson('/api/orders/'.$id)->assertOk()->assertJsonCount(1, 'data.items');
        $this->patchJson('/api/orders/'.$id, ['items' => [['product_name' => 'New', 'quantity' => 2, 'price' => '0.10']]])
            ->assertOk()->assertJsonPath('data.total_amount', '0.20')->assertJsonPath('data.items.0.product_name', 'New');
        $this->putJson('/api/orders/'.$id, ['customer_name' => 'New Customer'])
            ->assertOk()->assertJsonPath('data.customer_name', 'New Customer')->assertJsonPath('data.total_amount', '0.20');
        $this->deleteJson('/api/orders/'.$id)->assertNoContent();
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }

    public function test_status_transitions_and_editing_restrictions(): void
    {
        $order = Order::factory()->create(['user_id' => $this->owner->id]);
        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'confirmed'])->assertOk()->assertJsonPath('data.status', 'confirmed');
        $this->patchJson("/api/orders/{$order->id}", ['customer_name' => 'No'])->assertStatus(409)->assertJsonPath('code', 'ORDER_NOT_EDITABLE');
        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'pending'])->assertStatus(409)->assertJsonPath('code', 'INVALID_STATUS_TRANSITION');
        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'cancelled'])->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'confirmed'])->assertStatus(409);
    }

    public function test_filters_pagination_and_ownership_scoping(): void
    {
        Order::factory()->count(3)->create(['user_id' => $this->owner->id]);
        Order::factory()->confirmed()->count(2)->create(['user_id' => $this->owner->id]);
        $other = Order::factory()->create(['user_id' => User::factory()->create()->id]);
        $this->getJson('/api/orders?status=confirmed&per_page=1')->assertOk()->assertJsonPath('meta.total', 2)->assertJsonCount(1, 'data');
        $this->getJson('/api/orders')->assertOk()->assertJsonPath('meta.total', 5);
        $this->getJson('/api/orders?per_page=101')->assertUnprocessable();
        $this->getJson('/api/orders?status=unknown')->assertUnprocessable();
        $this->getJson("/api/orders/{$other->id}")->assertNotFound()->assertJsonPath('code', 'NOT_FOUND');
        $this->patchJson("/api/orders/{$other->id}", ['customer_name' => 'No'])->assertNotFound();
        $this->patchJson("/api/orders/{$other->id}/status", ['status' => 'confirmed'])->assertNotFound();
        $this->deleteJson("/api/orders/{$other->id}")->assertNotFound();
    }

    public function test_validation_and_overflow_do_not_persist_partial_orders(): void
    {
        $this->postJson('/api/orders', [...$this->payload(), 'items' => []])->assertUnprocessable();
        foreach ([['quantity' => 0, 'price' => '1.00'], ['quantity' => 1, 'price' => '-1.00'],
            ['quantity' => 1, 'price' => '1.001'], ['quantity' => 1, 'price' => 1.25],
            ['quantity' => 2, 'price' => '9999999999.99']] as $item) {
            $this->postJson('/api/orders', [...$this->payload(), 'items' => [['product_name' => 'Item', ...$item]]])
                ->assertUnprocessable()->assertJsonPath('code', 'VALIDATION_ERROR');
        }
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_failed_update_is_atomic_and_empty_updates_are_rejected(): void
    {
        $order = Order::factory()->create(['user_id' => $this->owner->id]);
        $this->patchJson("/api/orders/{$order->id}", [])->assertUnprocessable();
        $this->patchJson("/api/orders/{$order->id}", ['customer_name' => 'Changed', 'items' => [['product_name' => 'Overflow', 'quantity' => 2, 'price' => '9999999999.99']]])
            ->assertUnprocessable();
        $this->assertSame('39.98', $order->fresh()->total_amount);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertNotSame('Changed', $order->fresh()->customer_name);
    }

    public function test_payment_guard_blocks_deletion_and_successful_payment_cancellation(): void
    {
        $order = Order::factory()->confirmed()->create(['user_id' => $this->owner->id]);
        $this->app->instance(OrderPaymentGuardInterface::class, new PaymentGuard(true, true));
        $this->deleteJson("/api/orders/{$order->id}")->assertStatus(409)->assertJsonPath('code', 'ORDER_HAS_PAYMENTS');
        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'cancelled'])->assertStatus(409)->assertJsonPath('code', 'ORDER_HAS_PAYMENTS');
        $this->assertSame(OrderStatus::Confirmed, $order->fresh()->status);
    }

    public function test_reader_contract_and_policy_use_owner_identity(): void
    {
        $order = Order::factory()->confirmed()->create(['user_id' => $this->owner->id]);
        $reader = $this->app->make(OrderReaderInterface::class);
        $snapshot = DB::transaction(fn () => $reader->lockForPayment($order->id, $this->owner->id));
        $this->assertTrue($snapshot->payable);
        $this->assertSame('39.98', $snapshot->totalAmount);
        $this->assertTrue($this->owner->can('view', $order));
        $this->assertFalse(User::factory()->create()->can('view', $order));
    }

    public function test_orders_require_authentication_and_are_rate_limited(): void
    {
        config(['rate_limits.api_per_minute' => 1]);
        $this->getJson('/api/orders')->assertOk();
        $this->getJson('/api/orders')->assertStatus(429)->assertHeader('Retry-After');
        $this->resetAuthentication();
        $this->withHeaders(['Authorization' => ''])->get('/api/orders')->assertUnauthorized();
    }
}
