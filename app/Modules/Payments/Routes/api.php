<?php

declare(strict_types=1);

use App\Modules\Payments\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->group(function (): void {
    Route::post('orders/{order}/payments', [PaymentController::class, 'store'])
        ->whereNumber('order')->middleware(['throttle:payments', 'idempotent']);
    Route::middleware('throttle:api')->group(function (): void {
        Route::get('payments', [PaymentController::class, 'index']);
        Route::get('orders/{order}/payments', [PaymentController::class, 'forOrder'])->whereNumber('order');
        Route::get('payments/{payment}', [PaymentController::class, 'show'])->whereNumber('payment');
    });
});
