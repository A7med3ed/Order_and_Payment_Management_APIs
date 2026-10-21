<?php

declare(strict_types=1);

namespace App\Modules\Payments\Models;

use App\Modules\Orders\DTOs\OrderSnapshotData;
use App\Modules\Ledger\DTOs\LedgerEntryData;
use App\Modules\Ledger\Enums\{LedgerDirection, LedgerEntryType};
use App\Modules\Payments\Database\Factories\PaymentFactory;
use App\Modules\Payments\DTOs\ProcessPaymentData;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Events\{PaymentSucceeded, PaymentFailed};
use App\Modules\Payments\Exceptions\{InvalidPaymentTransitionException, OrderAlreadyPaidException, OrderNotConfirmedException};
use App\Modules\Payments\Gateways\DTOs\GatewayChargeResult;
use App\Modules\Shared\Exceptions\{ApiException, ErrorCode};
use App\Modules\Shared\Support\{Money, RecordsDomainEvents, SensitiveData};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;
    use RecordsDomainEvents;

    protected $guarded = ['*'];
    protected $hidden = ['gateway_response', 'request_hash', 'idempotency_key'];

    protected function casts(): array
    {
        return ['status' => PaymentStatus::class, 'amount' => 'decimal:2', 'order_id' => 'integer', 'user_id' => 'integer',
            'gateway_response' => 'array', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }

    protected static function newFactory(): PaymentFactory
    {
        return PaymentFactory::new();
    }

    public static function initiate(OrderSnapshotData $order, ProcessPaymentData $data, string $currency, bool $alreadyPaid = false, bool $inProgress = false): self
    {
        if (! $order->payable) {
            throw new OrderNotConfirmedException();
        }
        if ($alreadyPaid) {
            throw new OrderAlreadyPaidException();
        }
        if ($inProgress) {
            throw new ApiException(ErrorCode::PaymentInProgress, 409, 'This order has a pending payment. Wait for it to finish or reconcile it before retrying.', headers: ['Retry-After' => '1']);
        }
        if ($order->orderId !== $data->orderId || $order->userId !== $data->userId || $data->userId < 1
            || preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $data->method) !== 1
            || preg_match('/^[A-Za-z0-9_.:-]{8,255}$/D', $data->idempotencyKey) !== 1
            || preg_match('/^[A-Z]{3}$/D', $currency) !== 1
            || Money::compare($order->totalAmount, '0.00') < 0) {
            throw new InvalidArgumentException('Invalid payment identity, amount, currency, method, or idempotency reference.');
        }
        $payment = new self();
        $payment->order_id = $order->orderId;
        $payment->user_id = $order->userId;
        $payment->status = PaymentStatus::Pending;
        $payment->method = $data->method;
        $payment->amount = Money::format($order->totalAmount);
        $payment->currency = $currency;
        $payment->idempotency_key = $data->idempotencyKey;
        $payment->request_hash = $data->fingerprint();
        return $payment;
    }

    public function assertSameRequest(ProcessPaymentData $data): void
    {
        if ($this->user_id !== $data->userId || ! hash_equals($this->request_hash, $data->fingerprint())) {
            throw new ApiException(ErrorCode::IdempotencyKeyReused, 422, 'This key was already used for a different payment request.');
        }
    }

    public function applyGatewayResult(GatewayChargeResult $result): void
    {
        match ($result->successful) {
            true => $this->markSuccessful($result),
            false => $this->markFailed($result->errorCode, $result->message, $result->rawResponse),
        };
    }

    public function markSuccessful(GatewayChargeResult $result): void
    {
        if (! $result->successful) {
            throw new InvalidArgumentException('A successful gateway result is required.');
        }
        $this->transitionTo(PaymentStatus::Successful);
        $this->transaction_id = $result->transactionId;
        $this->gateway_response = SensitiveData::sanitize($result->rawResponse);
        $this->failure_code = null;
        $this->record(new PaymentSucceeded($this));
    }

    public function markFailed(string $code, string $message, array $response = []): void
    {
        $result = GatewayChargeResult::failure($code, $message, $response);
        $this->transitionTo(PaymentStatus::Failed);
        $this->transaction_id = null;
        $this->gateway_response = $result->rawResponse;
        $this->failure_code = $result->errorCode;
        $this->record(new PaymentFailed($this));
    }

    public function initiatedEntry(): LedgerEntryData
    {
        return $this->entry(LedgerEntryType::PaymentInitiated, LedgerDirection::Memo);
    }

    public function outcomeEntry(): LedgerEntryData
    {
        return match ($this->status) {
            PaymentStatus::Successful => $this->entry(LedgerEntryType::PaymentSucceeded, LedgerDirection::Credit),
            PaymentStatus::Failed => $this->entry(LedgerEntryType::PaymentFailed, LedgerDirection::Memo),
            default => throw new InvalidArgumentException('A pending payment has no outcome entry.'),
        };
    }

    private function entry(LedgerEntryType $type, LedgerDirection $direction): LedgerEntryData
    {
        return new LedgerEntryData(
            $this->order_id,
            $this->user_id,
            (int) $this->id,
            $type,
            $direction,
            $this->amount,
            $this->currency,
            $this->method,
            $this->transaction_id,
            $this->idempotency_key,
            array_filter(['provider' => $this->gateway_response, 'failure_code' => $this->failure_code], fn ($value) => $value !== null)
        );
    }

    private function transitionTo(PaymentStatus $status): void
    {
        if (! $this->status->canTransitionTo($status)) {
            throw new InvalidPaymentTransitionException($this->status, $status);
        }
        $this->status = $status;
    }
}
