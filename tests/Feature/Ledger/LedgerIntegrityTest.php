<?php

declare(strict_types=1);

namespace Tests\Feature\Ledger;

use App\Modules\Auth\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Ledger\Contracts\LedgerRecorderInterface;
use App\Modules\Ledger\DTOs\LedgerEntryData;
use App\Modules\Ledger\Enums\{LedgerEntryType, LedgerDirection};
use App\Modules\Ledger\Models\LedgerEntry;
use App\Modules\Ledger\Services\LedgerVerifier;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class LedgerIntegrityTest extends TestCase
{
    use DatabaseMigrations;

    public function test_tampering_breaks_hash_verification_and_the_console_command_fails(): void
    {
        $user = User::factory()->create();
        app(LedgerRecorderInterface::class)->record(new LedgerEntryData(1, $user->id, null, LedgerEntryType::PaymentInitiated, LedgerDirection::Memo, '1.00', 'USD'));
        DB::unprepared('DROP TRIGGER ledger_entries_no_update');
        try {
            DB::table('ledger_entries')->update(['amount' => '2.00']);
            $this->assertFalse(app(LedgerVerifier::class)->verify()->valid);
            $this->artisan('ledger:verify')->assertFailed();
        } finally {
            DB::unprepared("CREATE TRIGGER ledger_entries_no_update BEFORE UPDATE ON ledger_entries FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ledger entries are immutable'");
        }
    }

    public function test_deleted_tail_is_detected_by_the_chain_checkpoint(): void
    {
        $user = User::factory()->create();
        app(LedgerRecorderInterface::class)->record(new LedgerEntryData(1, $user->id, null, LedgerEntryType::PaymentInitiated, LedgerDirection::Memo, '1.00', 'USD'));
        DB::unprepared('DROP TRIGGER ledger_entries_no_delete');
        try {
            DB::table('ledger_entries')->delete();
            $this->assertFalse(app(LedgerVerifier::class)->verify()->valid);
        } finally {
            DB::unprepared("CREATE TRIGGER ledger_entries_no_delete BEFORE DELETE ON ledger_entries FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ledger entries are immutable'");
        }
    }

    public function test_simultaneous_first_appends_have_a_gap_free_sequence(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        $barrier = sys_get_temp_dir().'/ledger-'.bin2hex(random_bytes(12));
        $env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_HOST' => config('database.connections.mysql.host'),
            'DB_DATABASE' => 'app_testing', 'DB_USERNAME' => config('database.connections.mysql.username'),
            'DB_PASSWORD' => config('database.connections.mysql.password')];
        $workers = [];
        try {
            foreach ([1, 2] as $number) {
                $worker = new Process([PHP_BINARY, base_path('tests/Support/append-ledger.php'), (string) $order->id, (string) $user->id, $barrier, (string) $number], base_path(), $env);
                $worker->setTimeout(25);
                $worker->start();
                $workers[] = $worker;
            }
            $deadline = microtime(true) + 15;
            while (! file_exists($barrier.'.ready.1') || ! file_exists($barrier.'.ready.2')) {
                if (microtime(true) > $deadline) {
                    $this->fail('Workers did not reach the append barrier.');
                }
                usleep(10000);
                clearstatcache();
            }
            touch($barrier);
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertSame(0, $worker->getExitCode(), $worker->getErrorOutput().$worker->getOutput());
            }
            $this->assertSame([1, 2], LedgerEntry::query()->orderBy('chain_sequence')->pluck('chain_sequence')->all());
            $this->assertTrue(app(LedgerVerifier::class)->verify($order->id)->valid);
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
            foreach ([$barrier, $barrier.'.ready.1', $barrier.'.ready.2'] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
}
