<?php

declare(strict_types=1);

namespace App\Modules\Orders\Services;

use App\Modules\Orders\Contracts\OrderPaymentGuardInterface;
use App\Modules\Orders\DTOs\{CreateOrderData, UpdateOrderData, ChangeOrderStatusData, OrderFilterData, OrderIdentityData};
use App\Modules\Orders\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;

final readonly class OrderService
{
    public function __construct(private OrderPaymentGuardInterface $payments)
    {
    }

    public function create(CreateOrderData $data): Order
    {
        return DB::transaction(function () use ($data): Order {
            $order = Order::place($data->userId, $data->customerName, $data->customerEmail, $data->items);
            $order->save();
            $order->items()->saveMany($order->items);
            $this->dispatchAfterCommit($order);
            return $order->load('items');
        });
    }

    public function find(OrderIdentityData $data): Order
    {
        $order = Order::query()->with('items')->where('user_id', $data->userId)->findOrFail($data->orderId);
        Gate::authorize('view', $order);
        return $order;
    }

    public function list(OrderFilterData $data): LengthAwarePaginator
    {
        return Order::query()->with('items')->where('user_id', $data->userId)
            ->when($data->status, fn ($query, $status) => $query->where('status', $status->value))
            ->orderByDesc('id')->paginate($data->pagination->perPage, ['*'], 'page', $data->pagination->page)->withQueryString();
    }

    public function update(UpdateOrderData $data): Order
    {
        return DB::transaction(function () use ($data): Order {
            $order = $this->locked(new OrderIdentityData($data->orderId, $data->userId), 'update');
            $order->changeCustomerDetails($data->customerName ?? $order->customer_name, $data->customerEmail ?? $order->customer_email);
            if ($data->items !== null) {
                $order->replaceItems($data->items);
                $order->items()->delete();
                $order->items()->saveMany($order->items);
            }
            $order->save();
            $this->dispatchAfterCommit($order);
            return $order->load('items');
        });
    }

    public function changeStatus(ChangeOrderStatusData $data): Order
    {
        return DB::transaction(function () use ($data): Order {
            $order = $this->locked(new OrderIdentityData($data->orderId, $data->userId), 'update');
            $order->changeStatus($data->targetStatus, $this->payments);
            $order->save();
            $this->dispatchAfterCommit($order);
            return $order;
        });
    }

    public function delete(OrderIdentityData $data): void
    {
        DB::transaction(function () use ($data): void {
            $order = $this->locked($data, 'delete');
            $order->assertDeletable($this->payments);
            $order->delete();
        });
    }

    private function locked(OrderIdentityData $data, string $ability): Order
    {
        $order = Order::query()->with('items')->where('user_id', $data->userId)->lockForUpdate()->findOrFail($data->orderId);
        Gate::authorize($ability, $order);
        return $order;
    }

    private function dispatchAfterCommit(Order $order): void
    {
        $events = $order->releaseEvents();
        DB::afterCommit(static function () use ($events): void {
            foreach ($events as $event) {
                Event::dispatch($event);
            }
        });
    }
}
