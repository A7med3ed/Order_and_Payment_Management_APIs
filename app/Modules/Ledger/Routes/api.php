<?php

declare(strict_types=1);

use App\Modules\Ledger\Http\Controllers\LedgerController;
use Illuminate\Support\Facades\Route;

Route::get('orders/{order}/ledger', [LedgerController::class, 'index'])
    ->whereNumber('order')->middleware(['auth:api', 'throttle:api'])->name('ledger.order');

Route::get('payments/{payment}/ledger', [LedgerController::class, 'forPayment'])
    ->whereNumber('payment')->middleware(['auth:api', 'throttle:api'])->name('ledger.payment');
