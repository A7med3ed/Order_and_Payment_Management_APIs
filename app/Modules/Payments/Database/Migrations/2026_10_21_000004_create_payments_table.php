<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status', 16)->index();
            $table->string('method', 64)->index();
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3);
            $table->string('transaction_id')->nullable();
            $table->string('idempotency_key')->collation('utf8mb4_bin')->nullable();
            $table->char('request_hash', 64);
            $table->json('gateway_response')->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->timestamps();
            $table->unique(['order_id', 'idempotency_key']);
            $table->index(['user_id', 'id']);
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
