<?php

declare(strict_types=1);

namespace App\Modules\Payments\Services;

use App\Modules\Orders\Contracts\OrderReaderInterface;
use App\Modules\Ledger\Contracts\LedgerRecorderInterface;
use App\Modules\Payments\DTOs\{ProcessPaymentData, PaymentAttemptData, PaymentData, PaymentFilterData, PaymentIdentityData};
use App\Modules\Payments\Gateways\PaymentGatewayManager;
use App\Modules\Payments\Gateways\Contracts\PaymentGatewayInterface;
use App\Modules\Payments\Gateways\DTOs\{GatewayChargeRequest, GatewayChargeResult};
use App\Modules\Payments\Gateways\Exceptions\GatewayException;
use App\Modules\Payments\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\{DB, Event};

final readonly class PaymentService
{
    public function __construct(
        private OrderReaderInterface $orders,
        private OrderPaymentGuard $guard,
        private LedgerRecorderInterface $ledger,
        private PaymentGatewayManager $gateways,
    ) {
    }

    public function process(#[\SensitiveParameter] ProcessPaymentData $data): PaymentData
    {
        $gateway = $this->gateways->resolve($data->method);
        $attempt = DB::transaction(function () use ($data): PaymentAttemptData {
            $order = $this->orders->lockForPayment($data->orderId, $data->userId);
            $existing = Payment::query()->where('order_id', $data->orderId)->where('idempotency_key', $data->idempotencyKey)->first();
            $existing?->assertSameRequest($data);
            // This branch selects replay or creation; eligibility lives entirely in Payment::initiate.
            return match ($existing === null) {
                true => $this->persistNew(Payment::initiate(
                    $order,
                    $data,
                    (string) config('payment.currency'),
                    $this->guard->hasSuccessfulPayment($data->orderId),
                    $this->guard->hasPendingPayment($data->orderId)
                )),
                false => new PaymentAttemptData($existing, false),
            };
        }, 5);

        return match ($attempt->created) {
            false => PaymentData::fromModel($attempt->payment),
            true => $this->complete($attempt->payment, $this->charge($gateway, $attempt->payment, $data)),
        };
    }

    public function list(PaymentFilterData $data): LengthAwarePaginator
    {
        return Payment::query()->where('user_id', $data->userId)
            ->when($data->orderId, fn (Builder $query) => $query->where('order_id', $data->orderId))
            ->when($data->status, fn (Builder $query) => $query->where('status', $data->status))
            ->when($data->method !== null, fn (Builder $query) => $query->where('method', $data->method))
            ->latest('id')->paginate($data->pagination->perPage, ['*'], 'page', $data->pagination->page)
            ->through(fn (Payment $payment): PaymentData => PaymentData::fromModel($payment));
    }

    public function listForOrder(PaymentFilterData $data): LengthAwarePaginator
    {
        $this->orders->findOwned($data->orderId, $data->userId);
        return $this->list($data);
    }

    public function find(PaymentIdentityData $data): PaymentData
    {
        return PaymentData::fromModel(Payment::query()->where('user_id', $data->userId)->findOrFail($data->paymentId));
    }

    private function persistNew(Payment $payment): PaymentAttemptData
    {
        $payment->save();
        $this->ledger->record($payment->initiatedEntry());
        return new PaymentAttemptData($payment, true);
    }

    private function charge(PaymentGatewayInterface $gateway, Payment $payment, #[\SensitiveParameter] ProcessPaymentData $data): GatewayChargeResult
    {
        try {
            return $gateway->charge(new GatewayChargeRequest(
                (int) $payment->id,
                $payment->order_id,
                $payment->amount,
                $payment->currency,
                $data->idempotencyKey,
                $data->paymentDetails
            ));
        } catch (GatewayException) {
            return GatewayChargeResult::failure('GATEWAY_ERROR', 'The payment provider could not complete the request.');
        }
    }

    private function complete(Payment $payment, GatewayChargeResult $result): PaymentData
    {
        return DB::transaction(function () use ($payment, $result): PaymentData {
            $current = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $current->applyGatewayResult($result);
            $current->save();
            $this->ledger->record($current->outcomeEntry());
            $events = $current->releaseEvents();
            DB::afterCommit(static function () use ($events): void {
                foreach ($events as $event) {
                    Event::dispatch($event);
                }
            });
            return PaymentData::fromModel($current);
        }, 5);
    }
}
