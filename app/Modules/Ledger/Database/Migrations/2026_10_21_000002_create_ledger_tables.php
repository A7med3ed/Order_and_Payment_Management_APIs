<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('ledger_chain_heads', function (Blueprint $table): void {
            $table->unsignedBigInteger('order_id')->primary();
            $table->unsignedInteger('last_sequence')->default(0);
            $table->char('last_hash', 64)->nullable();
        });
        Schema::create('ledger_entries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->unsignedBigInteger('order_id')->index();
            $table->unsignedBigInteger('payment_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedInteger('chain_sequence');
            $table->string('type');
            $table->string('direction');
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3);
            $table->string('gateway')->nullable();
            $table->string('transaction_id')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->json('metadata');
            $table->char('previous_hash', 64)->nullable();
            $table->char('entry_hash', 64);
            $table->timestamp('created_at', 6);
            $table->unique(['order_id', 'chain_sequence']);
        });
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared("CREATE TRIGGER ledger_entries_no_update BEFORE UPDATE ON ledger_entries FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ledger entries are immutable'");
            DB::unprepared("CREATE TRIGGER ledger_entries_no_delete BEFORE DELETE ON ledger_entries FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ledger entries are immutable'");
        }
    }
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS ledger_entries_no_update');
            DB::unprepared('DROP TRIGGER IF EXISTS ledger_entries_no_delete');
        }
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('ledger_chain_heads');
    }
};
