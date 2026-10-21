<?php

declare(strict_types=1);

namespace Tests\Feature\Ledger;

use App\Modules\Auth\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Ledger\Contracts\LedgerRecorderInterface;
use App\Modules\Ledger\DTOs\LedgerEntryData;
use App\Modules\Ledger\Enums\{LedgerEntryType, LedgerDirection};
use App\Modules\Ledger\Exceptions\ImmutableLedgerException;
use App\Modules\Ledger\Models\LedgerEntry;
use App\Modules\Ledger\Services\LedgerVerifier;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class LedgerTest extends TestCase
{
    use RefreshDatabase;
    private User $owner;
    private Order $order;
    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->order = Order::factory()->create(['user_id' => $this->owner->id]);
    }

    private function append(LedgerEntryType $type = LedgerEntryType::PaymentInitiated, array $metadata = []): void
    {
        app(LedgerRecorderInterface::class)->record(new LedgerEntryData(
            $this->order->id,
            $this->owner->id,
            10,
            $type,
            $type->direction(),
            '39.98',
            'USD',
            'credit_card',
            metadata: $metadata
        ));
    }

    public function test_entries_form_a_hash_chain_and_financial_events_have_correct_directions(): void
    {
        $this->append();
        $this->append(LedgerEntryType::PaymentSucceeded);
        $this->append(LedgerEntryType::PaymentReversed);
        $entries = LedgerEntry::query()->orderBy('chain_sequence')->get();
        $this->assertSame([1, 2, 3], $entries->pluck('chain_sequence')->all());
        $this->assertNull($entries[0]->previous_hash);
        $this->assertSame($entries[0]->entry_hash, $entries[1]->previous_hash);
        $this->assertSame($entries[1]->entry_hash, $entries[2]->previous_hash);
        $this->assertSame(LedgerDirection::Credit, $entries[1]->direction);
        $this->assertSame(LedgerDirection::Debit, $entries[2]->direction);
        $this->assertTrue(app(LedgerVerifier::class)->verify()->valid);
        $this->artisan('ledger:verify')->assertSuccessful();
    }

    public function test_failed_payments_are_memos_and_metadata_is_sanitized(): void
    {
        $this->append(LedgerEntryType::PaymentFailed, ['last4' => '0002', 'card_number' => '4242424242420002',
            'cvv' => '123', 'nested' => ['secret' => 'hidden', 'brand' => 'visa']]);
        $entry = LedgerEntry::query()->sole();
        $this->assertSame(LedgerDirection::Memo, $entry->direction);
        $this->assertSame(['last4' => '0002', 'nested' => ['brand' => 'visa']], $entry->metadata);
        $this->assertTrue(app(LedgerVerifier::class)->verify()->valid);
    }

    public function test_existing_models_cannot_be_saved_even_without_changes(): void
    {
        $this->append();
        $entry = LedgerEntry::query()->sole();
        $this->expectException(ImmutableLedgerException::class);
        $entry->save();
    }

    public function test_existing_models_cannot_be_deleted(): void
    {
        $this->append();
        $this->expectException(ImmutableLedgerException::class);
        LedgerEntry::query()->sole()->delete();
    }

    public function test_bulk_updates_are_blocked_by_the_database_trigger(): void
    {
        $this->append();
        $this->expectException(QueryException::class);
        DB::table('ledger_entries')->update(['amount' => '0.01']);
    }

    public function test_bulk_deletes_are_blocked_by_the_database_trigger(): void
    {
        $this->append();
        $this->expectException(QueryException::class);
        LedgerEntry::query()->delete();
    }

    public function test_read_endpoint_is_owner_scoped_paginated_and_read_only(): void
    {
        $this->append();
        $this->append(LedgerEntryType::PaymentFailed);
        $token = $this->app['auth']->guard('api')->login($this->owner);
        $this->resetAuthentication();
        $this->withToken($token)->getJson("/api/orders/{$this->order->id}/ledger?per_page=1")
            ->assertOk()->assertJsonPath('meta.total', 2)->assertJsonCount(1, 'data')->assertJsonPath('data.0.chain_sequence', 1);
        $this->postJson("/api/orders/{$this->order->id}/ledger", [])->assertStatus(405);
        $this->getJson("/api/orders/{$this->order->id}/ledger?per_page=101")->assertUnprocessable();
        $other = $this->app['auth']->guard('api')->login(User::factory()->create());
        $this->resetAuthentication();
        $this->withToken($other)->getJson("/api/orders/{$this->order->id}/ledger")->assertNotFound();
    }

    public function test_direction_mismatch_cannot_append_a_financial_event(): void
    {
        try {
            app(LedgerRecorderInterface::class)->record(new LedgerEntryData(
                $this->order->id,
                $this->owner->id,
                null,
                LedgerEntryType::PaymentSucceeded,
                LedgerDirection::Debit,
                '1.00',
                'USD'
            ));
            $this->fail('Expected invalid event direction to be rejected.');
        } catch (\App\Modules\Shared\Exceptions\ApiException $exception) {
            $this->assertSame(422, $exception->httpStatus);
        }
        $this->assertDatabaseCount('ledger_entries', 0);
    }
}
