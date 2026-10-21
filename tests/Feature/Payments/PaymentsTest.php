<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Modules\Auth\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Models\Payment;
use App\Modules\Ledger\Models\LedgerEntry;
use App\Modules\Ledger\Services\LedgerVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class PaymentsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        config(['rate_limits.payments_per_minute' => 100, 'rate_limits.payments_per_order_per_minute' => 100]);
        $this->owner = User::factory()->create();
        $token = $this->app['auth']->guard('api')->login($this->owner);
        $this->resetAuthentication();
        $this->withToken($token);
    }

    private function order(): Order
    {
        return Order::factory()->confirmed()->create(['user_id' => $this->owner->id]);
    }

    private function card(string $number = '4242424242424242'): array
    {
        return ['method' => 'credit_card', 'payment_details' => ['card_number' => $number, 'expiry_month' => 12, 'expiry_year' => 2030, 'cvv' => '123', 'card_holder' => 'Jane Doe']];
    }

    public function test_success_persists_payment_two_ledger_entries_and_valid_chain_without_sensitive_data(): void
    {
        $order = $this->order();
        $response = $this->postJson("/api/orders/{$order->id}/payments", [...$this->card(), 'amount' => '0.01', 'user_id' => 999, 'status' => 'failed'], ['Idempotency-Key' => 'success-payment-01'])
            ->assertCreated()->assertJsonPath('data.status', 'successful')->assertJsonPath('data.amount', '39.98')->assertJsonPath('data.user_id', $this->owner->id);
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(['payment_initiated', 'payment_succeeded'], LedgerEntry::query()->orderBy('chain_sequence')->pluck('type')->map(fn ($type) => $type->value)->all());
        $this->assertTrue(app(LedgerVerifier::class)->verify($order->id)->valid);
        $stored = json_encode([DB::table('payments')->first(), DB::table('ledger_entries')->get()->all(), DB::table('idempotency_keys')->first()]);
        $this->assertStringNotContainsString('4242424242424242', $stored);
        $this->assertStringNotContainsString('"cvv"', $stored);
        $response->assertJsonMissingPath('data.gateway_response')->assertJsonMissingPath('data.request_hash')->assertJsonMissingPath('data.idempotency_key');
        $this->getJson('/api/payments/'.$response->json('data.id'))->assertOk()->assertJsonPath('data.status', 'successful');
    }

    public function test_decline_returns_201_and_allows_a_new_key_retry(): void
    {
        $order = $this->order();
        $this->postJson("/api/orders/{$order->id}/payments", $this->card('4000000000000002'), ['Idempotency-Key' => 'decline-payment-01'])
            ->assertCreated()->assertJsonPath('data.status', 'failed')->assertJsonPath('data.failure_code', 'CARD_DECLINED')->assertJsonPath('data.transaction_id', null);
        $this->assertSame('payment_failed', LedgerEntry::query()->latest('chain_sequence')->first()->type->value);
        $this->postJson("/api/orders/{$order->id}/payments", $this->card(), ['Idempotency-Key' => 'decline-retry-02'])
            ->assertCreated()->assertJsonPath('data.status', 'successful');
        $this->assertDatabaseCount('payments', 2);
        $this->assertDatabaseCount('ledger_entries', 4);
        $this->assertTrue(app(LedgerVerifier::class)->verify($order->id)->valid);
    }

    public function test_provider_exception_becomes_failed_payment_and_clean_response(): void
    {
        $order = $this->order();
        $this->postJson("/api/orders/{$order->id}/payments", $this->card('4000000000000003'), ['Idempotency-Key' => 'provider-error-01'])
            ->assertCreated()->assertJsonPath('data.status', 'failed')->assertJsonPath('data.failure_code', 'GATEWAY_ERROR');
        $this->assertDatabaseCount('ledger_entries', 2);
        $this->assertTrue(app(LedgerVerifier::class)->verify($order->id)->valid);
    }

    public function test_nonconfirmed_and_paid_orders_are_rejected_without_extra_financial_writes(): void
    {
        $pending = Order::factory()->create(['user_id' => $this->owner->id]);
        $this->postJson("/api/orders/{$pending->id}/payments", $this->card(), ['Idempotency-Key' => 'pending-order-01'])
            ->assertStatus(409)->assertJsonPath('code', 'ORDER_NOT_CONFIRMED');
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('ledger_entries', 0);
        $order = $this->order();
        $this->postJson("/api/orders/{$order->id}/payments", $this->card(), ['Idempotency-Key' => 'paid-order-01'])->assertCreated();
        $this->postJson("/api/orders/{$order->id}/payments", $this->card(), ['Idempotency-Key' => 'paid-order-02'])
            ->assertStatus(409)->assertJsonPath('code', 'ORDER_ALREADY_PAID');
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('ledger_entries', 2);
    }

    public function test_same_key_replays_identical_response_and_changed_payload_is_rejected(): void
    {
        $order = $this->order();
        $url = "/api/orders/{$order->id}/payments";
        $headers = ['Idempotency-Key' => 'replay-payment-01'];
        $first = $this->postJson($url, $this->card(), $headers)->assertCreated();
        $replay = $this->postJson($url, $this->card(), $headers)->assertCreated()->assertHeader('Idempotent-Replayed', 'true');
        $this->assertSame($first->getContent(), $replay->getContent());
        $this->postJson($url, $this->card('4000000000000002'), $headers)->assertUnprocessable()->assertJsonPath('code', 'IDEMPOTENCY_KEY_REUSED');
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('ledger_entries', 2);
    }

    public function test_missing_key_and_invalid_details_have_field_errors_and_validation_releases_key(): void
    {
        $url = '/api/orders/'.$this->order()->id.'/payments';
        $this->postJson($url, $this->card())->assertUnprocessable()->assertJsonPath('code', 'IDEMPOTENCY_KEY_REQUIRED');
        $headers = ['Idempotency-Key' => 'validation-retry-01'];
        $this->postJson($url, ['method' => 'credit_card', 'payment_details' => ['card_number' => 'invalid', 'cvv' => 'x', 'expiry_month' => 13, 'expiry_year' => 20]], $headers)
            ->assertUnprocessable()->assertJsonPath('code', 'VALIDATION_ERROR')->assertJsonValidationErrors(['payment_details.card_number', 'payment_details.cvv', 'payment_details.expiry_month', 'payment_details.expiry_year', 'payment_details.card_holder']);
        $this->assertDatabaseCount('idempotency_keys', 0);
        $this->postJson($url, ['method' => ['paypal'], 'payment_details' => []], $headers)->assertUnprocessable()->assertJsonValidationErrors('method');
        $this->postJson($url, ['method' => 'unsupported', 'payment_details' => []], $headers)->assertUnprocessable()->assertJsonValidationErrors('method');
        $this->postJson($url, ['method' => 'paypal', 'payment_details' => ['email' => 'invalid']], $headers)->assertUnprocessable()->assertJsonValidationErrors('payment_details.email');
        $this->postJson($url, ['method' => 'paypal', 'payment_details' => ['email' => 'payer@example.com', 'secret' => 'unexpected']], $headers)->assertUnprocessable()->assertJsonValidationErrors('payment_details');
        $this->assertDatabaseCount('payments', 0);
        $this->postJson($url, $this->card(), $headers)->assertCreated();
    }

    public function test_real_guard_blocks_deletion_and_cancellation_after_success(): void
    {
        $order = $this->order();
        $this->postJson("/api/orders/{$order->id}/payments", $this->card(), ['Idempotency-Key' => 'real-guard-01'])->assertCreated();
        $this->deleteJson("/api/orders/{$order->id}")->assertStatus(409)->assertJsonPath('code', 'ORDER_HAS_PAYMENTS');
        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'cancelled'])->assertStatus(409)->assertJsonPath('code', 'ORDER_HAS_PAYMENTS');
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'confirmed']);
    }

    public function test_failed_payment_also_blocks_deletion_but_allows_cancellation(): void
    {
        $order = $this->order();
        $this->postJson("/api/orders/{$order->id}/payments", $this->card('4000000000000002'), ['Idempotency-Key' => 'failed-guard-01'])->assertCreated();
        $this->deleteJson("/api/orders/{$order->id}")->assertStatus(409)->assertJsonPath('code', 'ORDER_HAS_PAYMENTS');
        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'cancelled'])->assertOk();
    }

    public function test_lists_filters_pagination_ownership_and_payment_ledger(): void
    {
        $first = $this->order();
        $second = $this->order();
        $payment = $this->postJson("/api/orders/{$first->id}/payments", $this->card('4000000000000002'), ['Idempotency-Key' => 'list-failed-01'])->assertCreated()->json('data.id');
        $this->postJson("/api/orders/{$second->id}/payments", ['method' => 'paypal', 'payment_details' => ['email' => 'payer@example.com']], ['Idempotency-Key' => 'list-success-02'])->assertCreated();
        $this->getJson('/api/payments?per_page=1')->assertOk()->assertJsonPath('meta.total', 2)->assertJsonCount(1, 'data');
        $this->getJson('/api/payments?status=failed&method=credit_card&order_id='.$first->id)->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $payment);
        $this->getJson("/api/orders/{$first->id}/payments")->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson("/api/payments/{$payment}/ledger?per_page=1")->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.type', 'payment_initiated');
        $this->getJson('/api/payments?status=invalid')->assertUnprocessable();
        $this->getJson('/api/payments?per_page=101')->assertUnprocessable();
        $this->getJson('/api/payments?order_id=-1')->assertUnprocessable();
        $other = Order::factory()->confirmed()->create();
        $otherPayment = Payment::factory()->successful()->create(['order_id' => $other->id, 'user_id' => $other->user_id]);
        $this->getJson('/api/payments')->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson("/api/orders/{$other->id}/payments")->assertNotFound();
        $this->postJson("/api/orders/{$other->id}/payments", $this->card(), ['Idempotency-Key' => 'other-owner-01'])->assertNotFound();
        $this->getJson("/api/payments/{$otherPayment->id}")->assertNotFound();
        $this->getJson("/api/payments/{$otherPayment->id}/ledger")->assertNotFound();
    }
}
