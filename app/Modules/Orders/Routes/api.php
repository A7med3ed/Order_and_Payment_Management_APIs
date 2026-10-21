<?php

declare(strict_types=1);

use App\Modules\Orders\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'throttle:api'])->name('orders.')->group(function (): void {
    Route::get('orders', [OrderController::class, 'index'])->name('index');
    Route::post('orders', [OrderController::class, 'store'])->name('store');
    Route::get('orders/{order}', [OrderController::class, 'show'])->whereNumber('order')->name('show');
    Route::match(['put', 'patch'], 'orders/{order}', [OrderController::class, 'update'])->whereNumber('order')->name('update');
    Route::patch('orders/{order}/status', [OrderController::class, 'changeStatus'])->whereNumber('order')->name('status');
    Route::delete('orders/{order}', [OrderController::class, 'destroy'])->whereNumber('order')->name('destroy');
});
