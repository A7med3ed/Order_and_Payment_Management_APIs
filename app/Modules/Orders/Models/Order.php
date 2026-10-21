<?php

declare(strict_types=1);

namespace App\Modules\Orders\Models;

use App\Modules\Orders\Contracts\OrderPaymentGuardInterface;
use App\Modules\Orders\Database\Factories\OrderFactory;
use App\Modules\Orders\Domain\Enums\OrderStatus;
use App\Modules\Orders\Domain\Events\{OrderPlaced, OrderConfirmed, OrderCancelled, OrderItemsReplaced};
use App\Modules\Orders\Domain\Exceptions\{EmptyOrderException, InvalidStatusTransitionException, OrderHasPaymentsException, OrderNotEditableException, InvalidOrderItemException};
use App\Modules\Orders\DTOs\OrderItemData;
use App\Modules\Shared\Support\Money;
use App\Modules\Shared\Support\RecordsDomainEvents;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;
use OverflowException;

final class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;
    use RecordsDomainEvents;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['status' => OrderStatus::class, 'total_amount' => 'decimal:2', 'user_id' => 'integer'];
    }

    protected static function newFactory(): OrderFactory
    {
        return OrderFactory::new();
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @param list<OrderItemData> $items */
    public static function place(int $userId, string $customerName, string $customerEmail, array $items): self
    {
        $order = new self();
        $order->user_id = $userId;
        $order->status = OrderStatus::Pending;
        $order->changeCustomerDetails($customerName, $customerEmail);
        $order->applyItems($items);
        $order->record(new OrderPlaced($order));
        return $order;
    }

    public function confirm(): void
    {
        $this->transitionTo(OrderStatus::Confirmed);
        $this->record(new OrderConfirmed($this));
    }

    public function changeStatus(OrderStatus $target, OrderPaymentGuardInterface $payments): void
    {
        match ($target) {
            OrderStatus::Confirmed => $this->confirm(),
            OrderStatus::Cancelled => $this->cancel($payments),
            default => throw new InvalidStatusTransitionException($this->status, $target),
        };
    }

    public function cancel(OrderPaymentGuardInterface $payments): void
    {
        if ($this->exists && $payments->hasSuccessfulPayment((int) $this->getKey())) {
            throw OrderHasPaymentsException::cannotCancel();
        }
        $this->transitionTo(OrderStatus::Cancelled);
        $this->record(new OrderCancelled($this));
    }

    /** @param list<OrderItemData> $items */
    public function replaceItems(array $items): void
    {
        $this->assertEditable();
        $this->applyItems($items);
        $this->record(new OrderItemsReplaced($this));
    }

    public function changeCustomerDetails(string $name, string $email): void
    {
        $this->assertEditable();
        $this->customer_name = $name;
        $this->customer_email = $email;
    }

    public function assertDeletable(OrderPaymentGuardInterface $payments): void
    {
        if ($this->exists && $payments->hasPayments((int) $this->getKey())) {
            throw new OrderHasPaymentsException();
        }
    }

    public function canBeEdited(): bool
    {
        return $this->status === OrderStatus::Pending;
    }
    public function canBePaid(): bool
    {
        return $this->status === OrderStatus::Confirmed;
    }
    public function isOwnedBy(int $userId): bool
    {
        return $this->user_id === $userId;
    }

    private function assertEditable(): void
    {
        if (! $this->canBeEdited()) {
            throw new OrderNotEditableException();
        }
    }

    private function transitionTo(OrderStatus $target): void
    {
        if (! $this->status->canTransitionTo($target)) {
            throw new InvalidStatusTransitionException($this->status, $target);
        }
        $this->status = $target;
    }

    /** @param list<OrderItemData> $items */
    private function applyItems(array $items): void
    {
        if ($items === []) {
            throw new EmptyOrderException();
        }
        $children = new Collection();
        $total = '0.00';
        foreach ($items as $item) {
            if (! $item instanceof OrderItemData || trim($item->productName) === ''
                || mb_strlen($item->productName) > 255 || $item->quantity < 1 || $item->quantity > 4294967295) {
                throw new InvalidOrderItemException('Each item needs a product name and a positive quantity within database capacity.');
            }
            try {
                $price = Money::format($item->price);
                if (Money::compare($price, '0.00') < 0) {
                    throw new InvalidArgumentException('Negative price.');
                }
                $total = Money::add($total, Money::mul($price, $item->quantity));
            } catch (InvalidArgumentException|OverflowException $exception) {
                throw new InvalidOrderItemException('Prices and totals must be non-negative decimal strings within DECIMAL(12,2) capacity.', $exception);
            }
            $child = new OrderItem();
            $child->setRawAttributes(['product_name' => $item->productName, 'quantity' => $item->quantity, 'price' => $price]);
            $children->push($child);
        }
        $this->setRelation('items', $children);
        $this->total_amount = $total;
    }
}
