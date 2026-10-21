<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Modules\Auth\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Ledger\Contracts\LedgerRecorderInterface;
use App\Modules\Ledger\DTOs\LedgerEntryData;
use App\Modules\Ledger\Services\{LedgerService, LedgerVerifier};
use App\Modules\Payments\DTOs\ProcessPaymentData;
use App\Modules\Payments\Enums\PaymentStatus;
use App\Modules\Payments\Events\{PaymentSucceeded, PaymentFailed};
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Gateways\Contracts\PaymentGatewayInterface;
use App\Modules\Payments\Gateways\PaymentGatewayManager;
use App\Modules\Payments\Gateways\DTOs\{GatewayChargeRequest, GatewayChargeResult};
use App\Modules\Payments\Services\PaymentService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\{DB, Event};
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class PaymentTransactionsTest extends TestCase
{
    use DatabaseMigrations;

    private function data(): ProcessPaymentData
    {
        $user = User::factory()->create();
        $order = Order::factory()->confirmed()->create(['user_id' => $user->id]);
        return new ProcessPaymentData($order->id, $user->id, 'paypal', ['email' => 'payer@example.com'], 'transaction-test-01');
    }

    public static function outcomes(): array
    {
        return [[true], [false]];
    }

    #[DataProvider('outcomes')]
    public function test_service_coordinates_mocked_gateway_and_ledger_in_order_and_dispatches_after_commit(bool $success): void
    {
        $data = $this->data();
        $calls = [];
        $realLedger = app(LedgerService::class);
        $ledger = Mockery::mock(LedgerRecorderInterface::class);
        $ledger->shouldReceive('record')->twice()->andReturnUsing(function (LedgerEntryData $entry) use (&$calls, $realLedger): void {
            $this->assertGreaterThan(0, DB::transactionLevel());
            $this->assertSame($entry->type->value === 'payment_initiated' ? PaymentStatus::Pending : ($entry->type->value === 'payment_succeeded' ? PaymentStatus::Successful : PaymentStatus::Failed), Payment::findOrFail($entry->paymentId)->status);
            $calls[] = $entry->type->value;
            $realLedger->record($entry);
        });
        $this->app->instance(LedgerRecorderInterface::class, $ledger);
        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('method')->andReturn('paypal');
        $gateway->shouldReceive('charge')->once()->andReturnUsing(function (GatewayChargeRequest $request) use (&$calls, $success, $data): GatewayChargeResult {
            $this->assertSame(0, DB::transactionLevel());
            $this->assertDatabaseCount('payments', 1);
            $this->assertDatabaseCount('ledger_entries', 1);
            $this->assertSame($data->idempotencyKey, $request->idempotencyKey);
            $this->assertSame('39.98', $request->amount);
            $calls[] = 'charge';
            return $success ? GatewayChargeResult::success('mocked-provider-001') : GatewayChargeResult::failure('DECLINED', 'Declined.');
        });
        app(PaymentGatewayManager::class)->extend('paypal', fn () => $gateway);
        Event::listen($success ? PaymentSucceeded::class : PaymentFailed::class, function () use (&$calls): void {
            $this->assertSame(0, DB::transactionLevel());
            $this->assertDatabaseCount('ledger_entries', 2);
            $calls[] = 'event';
        });
        $payment = app(PaymentService::class)->process($data);
        $this->assertSame($success ? PaymentStatus::Successful : PaymentStatus::Failed, $payment->status);
        $this->assertSame(['payment_initiated', 'charge', $success ? 'payment_succeeded' : 'payment_failed', 'event'], $calls);
        $this->assertTrue(app(LedgerVerifier::class)->verify($data->orderId)->valid);
        $this->artisan('ledger:verify')->assertSuccessful();
    }

    public function test_initial_ledger_failure_rolls_back_payment_and_never_calls_gateway(): void
    {
        $data = $this->data();
        $ledger = Mockery::mock(LedgerRecorderInterface::class);
        $ledger->shouldReceive('record')->once()->andThrow(new \RuntimeException('Simulated storage failure.'));
        $this->app->instance(LedgerRecorderInterface::class, $ledger);
        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('method')->andReturn('paypal');
        $gateway->shouldNotReceive('charge');
        app(PaymentGatewayManager::class)->extend('paypal', fn () => $gateway);
        Event::fake([PaymentSucceeded::class, PaymentFailed::class]);
        try {
            app(PaymentService::class)->process($data);
            $this->fail('Expected storage failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated storage failure.', $exception->getMessage());
        }
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('ledger_entries', 0);
        Event::assertNotDispatched(PaymentSucceeded::class);
        Event::assertNotDispatched(PaymentFailed::class);
    }

    public function test_final_ledger_failure_keeps_pending_attempt_and_retry_never_charges_again(): void
    {
        $data = $this->data();
        $realLedger = app(LedgerService::class);
        $ledger = Mockery::mock(LedgerRecorderInterface::class);
        $ledger->shouldReceive('record')->twice()->andReturnUsing(function (LedgerEntryData $entry) use ($realLedger): void {
            if ($entry->type->value !== 'payment_initiated') {
                throw new \RuntimeException('Simulated outcome storage failure.');
            }
            $realLedger->record($entry);
        });
        $this->app->instance(LedgerRecorderInterface::class, $ledger);
        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('method')->andReturn('paypal');
        $gateway->shouldReceive('charge')->once()->andReturn(GatewayChargeResult::success('already-charged-001'));
        app(PaymentGatewayManager::class)->extend('paypal', fn () => $gateway);
        Event::fake([PaymentSucceeded::class, PaymentFailed::class]);
        try {
            app(PaymentService::class)->process($data);
            $this->fail('Expected outcome storage failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated outcome storage failure.', $exception->getMessage());
        }
        $this->assertDatabaseHas('payments', ['status' => 'pending', 'transaction_id' => null]);
        $this->assertDatabaseCount('ledger_entries', 1);
        Event::assertNotDispatched(PaymentSucceeded::class);
        $this->assertSame(PaymentStatus::Pending, app(PaymentService::class)->process($data)->status);
        $this->assertDatabaseCount('payments', 1);
        $this->assertTrue(app(LedgerVerifier::class)->verify($data->orderId)->valid);
    }

    public static function concurrentKeys(): array
    {
        return [[true], [false]];
    }

    #[DataProvider('concurrentKeys')]
    public function test_concurrent_http_attempts_charge_once_with_same_or_different_keys(bool $sameKey): void
    {
        $data = $this->data();
        $user = User::findOrFail($data->userId);
        $token = $this->app['auth']->guard('api')->login($user);
        $this->resetAuthentication();
        $barrier = sys_get_temp_dir().'/payments-'.bin2hex(random_bytes(12));
        $env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_HOST' => config('database.connections.mysql.host'),
            'DB_DATABASE' => 'app_testing', 'DB_USERNAME' => config('database.connections.mysql.username'),
            'DB_PASSWORD' => config('database.connections.mysql.password'), 'PAYMENT_TEST_TOKEN' => $token];
        $workers = [];
        try {
            foreach ([1, 2] as $number) {
                $key = 'concurrent-payment-'.($sameKey ? 'shared' : $number);
                $worker = new Process([PHP_BINARY, base_path('tests/Support/process-payment.php'), (string) $data->orderId, $barrier, (string) $number, $key], base_path(), $env);
                $worker->setTimeout(30);
                $worker->start();
                $workers[] = $worker;
            }
            $deadline = microtime(true) + 15;
            while (! file_exists($barrier.'.ready.1') || ! file_exists($barrier.'.ready.2')) {
                if (microtime(true) > $deadline) {
                    $this->fail('Payment workers did not reach the barrier.');
                }
                usleep(10000);
                clearstatcache();
            }
            touch($barrier);
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertSame(0, $worker->getExitCode(), $worker->getErrorOutput().$worker->getOutput());
            }
            $responses = [json_decode(file_get_contents($barrier.'.result.1'), true, flags: JSON_THROW_ON_ERROR), json_decode(file_get_contents($barrier.'.result.2'), true, flags: JSON_THROW_ON_ERROR)];
            $this->assertContains(201, array_column($responses, 'status'));
            foreach ($responses as $response) {
                $this->assertContains($response['status'], [201, 409]);
                if ($response['status'] === 409) {
                    $this->assertContains($response['code'], ['IDEMPOTENCY_IN_PROGRESS', 'PAYMENT_IN_PROGRESS', 'ORDER_ALREADY_PAID']);
                }
            }
            $this->assertSame(1, count(file($barrier.'.charges', FILE_IGNORE_NEW_LINES)));
            $this->assertDatabaseCount('payments', 1);
            $this->assertDatabaseHas('payments', ['status' => 'successful']);
            $this->assertDatabaseCount('ledger_entries', 2);
            $this->assertTrue(app(LedgerVerifier::class)->verify($data->orderId)->valid);
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
            foreach ([$barrier, $barrier.'.ready.1', $barrier.'.ready.2', $barrier.'.result.1', $barrier.'.result.2', $barrier.'.charges'] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
}
