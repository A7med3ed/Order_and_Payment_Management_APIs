<?php

declare(strict_types=1);

namespace Tests\Unit\Orders;

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\DTOs\OrderItemData;
use App\Modules\Orders\Domain\Enums\OrderStatus;
use App\Modules\Orders\Domain\Events\{OrderPlaced, OrderConfirmed, OrderCancelled, OrderItemsReplaced};
use App\Modules\Orders\Domain\Exceptions\{EmptyOrderException, InvalidStatusTransitionException, OrderHasPaymentsException, OrderNotEditableException, InvalidOrderItemException};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\PaymentGuard;

final class OrderTest extends TestCase
{
    private function order(): Order
    {
        return Order::place(7, 'Jane', 'jane@example.com', [new OrderItemData('Product', 3, '19.99')]);
    }

    public function test_placing_an_order_computes_totals_and_records_an_event_without_a_database(): void
    {
        $order = $this->order();
        $this->assertSame('59.97', $order->total_amount);
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertTrue($order->canBeEdited());
        $this->assertFalse($order->canBePaid());
        $this->assertTrue($order->isOwnedBy(7));
        $this->assertFalse($order->isOwnedBy(8));
        $this->assertInstanceOf(OrderPlaced::class, $order->releaseEvents()[0]);
        $this->assertSame([], $order->releaseEvents());
    }

    public function test_confirm_cancel_and_terminal_state(): void
    {
        $order = $this->order();
        $order->confirm();
        $this->assertTrue($order->canBePaid());
        $order->cancel(new PaymentGuard());
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $events = $order->releaseEvents();
        $this->assertInstanceOf(OrderConfirmed::class, $events[1]);
        $this->assertInstanceOf(OrderCancelled::class, $events[2]);
        $this->expectException(InvalidStatusTransitionException::class);
        $order->confirm();
    }

    public function test_pending_orders_can_be_cancelled_directly(): void
    {
        $order = $this->order();
        $order->changeStatus(OrderStatus::Cancelled, new PaymentGuard());
        $this->assertSame(OrderStatus::Cancelled, $order->status);
    }

    public function test_customer_and_items_changes_recalculate_totals_and_record_events(): void
    {
        $order = $this->order();
        $order->changeCustomerDetails('New name', 'new@example.com');
        $order->replaceItems([new OrderItemData('Other', 2, '0.10'), new OrderItemData('More', 1, '0.20')]);
        $this->assertSame('0.40', $order->total_amount);
        $this->assertSame('New name', $order->customer_name);
        $this->assertInstanceOf(OrderItemsReplaced::class, $order->releaseEvents()[1]);
    }

    #[DataProvider('editOperations')]
    public function test_non_pending_orders_cannot_be_edited(string $operation): void
    {
        $order = $this->order();
        $order->confirm();
        $this->expectException(OrderNotEditableException::class);
        match ($operation) {
            'items' => $order->replaceItems([new OrderItemData('New', 1, '1.00')]),
            'customer' => $order->changeCustomerDetails('New', 'new@example.com'),
        };
    }
    public static function editOperations(): array
    {
        return [['items'], ['customer']];
    }

    public function test_orders_with_successful_payments_cannot_be_cancelled(): void
    {
        $order = $this->order();
        $order->id = 5;
        $order->exists = true;
        $order->confirm();
        $this->expectException(OrderHasPaymentsException::class);
        $order->cancel(new PaymentGuard(true, true));
    }

    public function test_failed_payments_do_not_prevent_cancellation(): void
    {
        $order = $this->order();
        $order->id = 5;
        $order->exists = true;
        $order->confirm();
        $order->cancel(new PaymentGuard(true, false));
        $this->assertSame(OrderStatus::Cancelled, $order->status);
    }

    public function test_pending_payments_prevent_cancellation_during_the_gateway_call(): void
    {
        $order = $this->order();
        $order->id = 5;
        $order->exists = true;
        $order->confirm();
        $this->expectException(OrderHasPaymentsException::class);
        $order->cancel(new PaymentGuard(true, false, true));
    }

    public function test_any_payment_blocks_deletion(): void
    {
        $order = $this->order();
        $order->id = 5;
        $order->exists = true;
        $order->assertDeletable(new PaymentGuard());
        $this->expectException(OrderHasPaymentsException::class);
        $order->assertDeletable(new PaymentGuard(true));
    }

    public function test_an_order_cannot_be_empty(): void
    {
        $this->expectException(EmptyOrderException::class);
        Order::place(7, 'Jane', 'jane@example.com', []);
    }

    #[DataProvider('badItems')]
    public function test_invalid_item_values_are_rejected(int $quantity, string $price): void
    {
        $this->expectException(InvalidOrderItemException::class);
        Order::place(7, 'Jane', 'jane@example.com', [new OrderItemData('Product', $quantity, $price)]);
    }
    public static function badItems(): array
    {
        return [[0, '1.00'], [1, '-0.01'], [1, '1.001'], [2, '9999999999.99'], [4294967296, '0.00']];
    }

    public function test_protected_fields_are_not_mass_assignable(): void
    {
        $order = new Order();
        $this->assertFalse($order->isFillable('status'));
        $this->assertFalse($order->isFillable('total_amount'));
    }

    public function test_all_state_machine_pairs(): void
    {
        foreach (OrderStatus::cases() as $from) {
            foreach (OrderStatus::cases() as $to) {
                $allowed = in_array($from->value.':'.$to->value, ['pending:confirmed', 'pending:cancelled', 'confirmed:cancelled'], true);
                $this->assertSame($allowed, $from->canTransitionTo($to));
            }
        }
    }
}
