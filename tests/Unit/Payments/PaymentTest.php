<?php

declare(strict_types=1);

namespace Tests\Unit\Payments;

use App\Modules\Orders\DTOs\OrderSnapshotData;
use App\Modules\Payments\DTOs\ProcessPaymentData;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Exceptions\{InvalidPaymentTransitionException, OrderNotConfirmedException, OrderAlreadyPaidException};
use App\Modules\Payments\Gateways\DTOs\GatewayChargeResult;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Events\{PaymentSucceeded, PaymentFailed};
use App\Modules\Shared\Exceptions\ApiException;
use Illuminate\Database\Eloquent\MassAssignmentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PaymentTest extends TestCase
{
    private function data(array $details = ['email' => 'payer@example.com']): ProcessPaymentData
    {
        return new ProcessPaymentData(12, 7, 'paypal', $details, 'payment-test-001');
    }

    private function payment(): Payment
    {
        return Payment::initiate(new OrderSnapshotData(12, 7, '39.98', true), $this->data(), 'USD');
    }

    public function test_initiation_and_success_in_memory_with_sanitized_response_and_event(): void
    {
        $payment = $this->payment();
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame('39.98', $payment->amount);
        $this->assertSame('USD', $payment->currency);
        $payment->markSuccessful(GatewayChargeResult::success('transaction-1', raw: ['last4' => '4242', 'card_number' => '4242424242424242', 'cvv' => '123', 'nested' => ['api_key' => 'secret', 'brand' => 'visa']]));
        $this->assertSame(PaymentStatus::Successful, $payment->status);
        $this->assertSame(['last4' => '4242', 'nested' => ['brand' => 'visa']], $payment->gateway_response);
        $this->assertInstanceOf(PaymentSucceeded::class, $payment->releaseEvents()[0]);
        $this->assertSame([], $payment->releaseEvents());
        $this->assertSame('credit', $payment->outcomeEntry()->direction->value);
    }

    public function test_failure_records_only_sanitized_data_and_domain_event(): void
    {
        $payment = $this->payment();
        $payment->applyGatewayResult(GatewayChargeResult::failure('CARD_DECLINED', 'Sensitive provider text is not persisted.', ['credentials' => ['secret' => 'hidden'], 'last4' => '0002']));
        $this->assertSame(PaymentStatus::Failed, $payment->status);
        $this->assertSame('CARD_DECLINED', $payment->failure_code);
        $this->assertNull($payment->transaction_id);
        $this->assertSame(['last4' => '0002'], $payment->gateway_response);
        $this->assertInstanceOf(PaymentFailed::class, $payment->releaseEvents()[0]);
        $this->assertSame('memo', $payment->outcomeEntry()->direction->value);
    }

    public static function transitions(): array
    {
        return [
            [PaymentStatus::Pending, PaymentStatus::Pending, false],
            [PaymentStatus::Pending, PaymentStatus::Successful, true],
            [PaymentStatus::Pending, PaymentStatus::Failed, true],
            [PaymentStatus::Successful, PaymentStatus::Pending, false],
            [PaymentStatus::Successful, PaymentStatus::Successful, false],
            [PaymentStatus::Successful, PaymentStatus::Failed, false],
            [PaymentStatus::Failed, PaymentStatus::Pending, false],
            [PaymentStatus::Failed, PaymentStatus::Successful, false],
            [PaymentStatus::Failed, PaymentStatus::Failed, false],
        ];
    }

    #[DataProvider('transitions')]
    public function test_payment_state_machine(PaymentStatus $from, PaymentStatus $to, bool $allowed): void
    {
        $this->assertSame($allowed, $from->canTransitionTo($to));
    }

    public static function terminalChanges(): array
    {
        return [[true, true], [true, false], [false, true], [false, false]];
    }

    #[DataProvider('terminalChanges')]
    public function test_terminal_aggregate_cannot_change(bool $firstSuccess, bool $nextSuccess): void
    {
        $payment = $this->payment();
        $payment->applyGatewayResult($firstSuccess ? GatewayChargeResult::success('tx-1') : GatewayChargeResult::failure('DECLINED', 'Declined.'));
        $this->expectException(InvalidPaymentTransitionException::class);
        $payment->applyGatewayResult($nextSuccess ? GatewayChargeResult::success('tx-2') : GatewayChargeResult::failure('DECLINED', 'Declined.'));
    }

    public function test_non_confirmed_order_is_rejected_by_the_aggregate(): void
    {
        $this->expectException(OrderNotConfirmedException::class);
        Payment::initiate(new OrderSnapshotData(12, 7, '39.98', false), $this->data(), 'USD');
    }

    public function test_paid_order_is_rejected_by_the_aggregate(): void
    {
        $this->expectException(OrderAlreadyPaidException::class);
        Payment::initiate(new OrderSnapshotData(12, 7, '39.98', true), $this->data(), 'USD', true);
    }

    public function test_pending_attempt_blocks_a_second_key(): void
    {
        try {
            Payment::initiate(new OrderSnapshotData(12, 7, '39.98', true), $this->data(), 'USD', false, true);
            $this->fail('Expected an in-progress conflict.');
        } catch (ApiException $exception) {
            $this->assertSame('PAYMENT_IN_PROGRESS', $exception->errorCode->value);
        }
    }

    public function test_persistent_fingerprint_rejects_changed_details(): void
    {
        $payment = $this->payment();
        $payment->assertSameRequest($this->data());
        $this->expectException(ApiException::class);
        $payment->assertSameRequest($this->data(['email' => 'someone@example.com']));
    }

    public function test_status_and_amount_are_not_mass_assignable(): void
    {
        $this->expectException(MassAssignmentException::class);
        new Payment(['status' => 'successful', 'amount' => '0.01']);
    }

    public function test_invalid_success_is_rejected_without_mutating_state(): void
    {
        $payment = $this->payment();
        try {
            $payment->markSuccessful(GatewayChargeResult::success(''));
            $this->fail('Expected invalid transaction reference.');
        } catch (\InvalidArgumentException) {
            $this->assertSame(PaymentStatus::Pending, $payment->status);
            $this->assertSame([], $payment->releaseEvents());
        }
    }

    public function test_invalid_failure_code_is_rejected_without_mutating_state(): void
    {
        $payment = $this->payment();
        try {
            $payment->markFailed('bad code', 'Invalid.');
            $this->fail('Expected invalid failure code.');
        } catch (\InvalidArgumentException) {
            $this->assertSame(PaymentStatus::Pending, $payment->status);
            $this->assertSame([], $payment->releaseEvents());
        }
    }
}
