<?php

declare(strict_types=1);

namespace App\Modules\Payments\Database\Factories;

use App\Modules\Orders\Contracts\OrderReaderInterface;
use App\Modules\Payments\DTOs\ProcessPaymentData;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Gateways\DTOs\GatewayChargeResult;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Payment> */
final class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return ['method' => 'paypal', 'idempotency_key' => (string) Str::uuid(), 'details' => ['email' => 'payer@example.com']];
    }

    public function newModel(array $attributes = []): Payment
    {
        if ($attributes === []) {
            return new Payment();
        }
        // The caller supplies a confirmed order; cross-module reads use its contract.
        $order = app(OrderReaderInterface::class)->findOwned((int) $attributes['order_id'], (int) $attributes['user_id']);
        return Payment::initiate($order, new ProcessPaymentData($order->orderId, $order->userId, $attributes['method'], $attributes['details'], $attributes['idempotency_key']), (string) config('payment.currency'));
    }

    public function successful(): static
    {
        return $this->afterMaking(fn (Payment $payment) => $payment->markSuccessful(GatewayChargeResult::success('fixture_'.$payment->idempotency_key)));
    }

    public function failed(): static
    {
        return $this->afterMaking(fn (Payment $payment) => $payment->markFailed('CARD_DECLINED', 'Declined.'));
    }

    public function configure(): static
    {
        return $this->afterCreating(fn (Payment $payment) => $payment->releaseEvents());
    }
}
