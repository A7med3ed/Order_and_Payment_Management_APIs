<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use App\Modules\Auth\Models\User;
use App\Modules\Orders\Domain\Events\OrderPlaced;
use App\Modules\Orders\DTOs\{CreateOrderData, OrderItemData};
use App\Modules\Orders\Services\OrderService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class OrderEventsTest extends TestCase
{
    use DatabaseMigrations;
    public function test_events_are_dispatched_only_after_commit_and_not_on_rollback(): void
    {
        Event::fake([OrderPlaced::class]);
        $user = User::factory()->create();
        $data = new CreateOrderData($user->id, 'Customer', 'customer@example.com', [new OrderItemData('Item', 1, '1.00')]);
        DB::beginTransaction();
        $order = app(OrderService::class)->create($data);
        Event::assertNotDispatched(OrderPlaced::class);
        DB::commit();
        Event::assertDispatched(OrderPlaced::class, fn ($event) => $event->order->id === $order->id);

        Event::fake([OrderPlaced::class]);
        DB::beginTransaction();
        app(OrderService::class)->create($data);
        DB::rollBack();
        Event::assertNotDispatched(OrderPlaced::class);
        $this->assertDatabaseCount('orders', 1);
    }
}
