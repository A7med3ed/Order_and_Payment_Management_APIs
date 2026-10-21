<?php

declare(strict_types=1);

namespace App\Modules\Orders\Database\Factories;

use App\Modules\Orders\DTOs\OrderItemData;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\NullOrderPaymentGuard;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** @extends Factory<Order> */
final class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'user_id' => fn (): int => DB::table('users')->insertGetId([
                'name' => fake()->name(), 'email' => fake()->unique()->safeEmail(), 'password' => Hash::make('password'),
                'created_at' => now(), 'updated_at' => now(),
            ]),
            'customer_name' => fake()->name(), 'customer_email' => fake()->safeEmail(),
            'items' => [new OrderItemData('Example product', 2, '19.99')],
        ];
    }

    public function newModel(array $attributes = []): Order
    {
        if ($attributes === []) {
            return new Order();
        }
        return Order::place((int) $attributes['user_id'], $attributes['customer_name'], $attributes['customer_email'], $attributes['items']);
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Order $order): void {
            $order->items()->saveMany($order->items);
            $order->releaseEvents();
        });
    }

    public function pending(): static
    {
        return $this;
    }
    public function confirmed(): static
    {
        return $this->afterMaking(fn (Order $order) => $order->confirm());
    }
    public function cancelled(): static
    {
        return $this->afterMaking(fn (Order $order) => $order->cancel(new NullOrderPaymentGuard()));
    }
}
