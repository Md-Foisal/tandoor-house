<?php

use App\Http\Controllers\Staff\Orders\OrderBoardController;
use App\Http\Controllers\Staff\Orders\OrderEditController;
use App\Http\Controllers\Staff\Orders\OrderPaymentController;
use App\Http\Controllers\Staff\Orders\OrderReceiptController;
use App\Http\Controllers\Staff\Orders\OrderRefundController;
use App\Http\Controllers\Staff\Orders\OrderRiderController;
use App\Http\Controllers\Staff\Orders\OrderTransitionController;
use Illuminate\Support\Facades\Route;

Route::prefix('orders')->name('orders.')->group(function () {
    Route::get('/', [OrderBoardController::class, 'index'])->name('index');
    Route::get('/{order:number}', [OrderBoardController::class, 'show'])->name('show');
    Route::post('/{order:number}/status', OrderTransitionController::class)->name('status');
    Route::patch('/{order:number}/lines', OrderEditController::class)->name('edit')->middleware('throttle:staff-sensitive');
    Route::post('/{order:number}/refund', OrderRefundController::class)->name('refund')->middleware('throttle:staff-sensitive');
    Route::post('/{order:number}/payment', OrderPaymentController::class)->name('payment')->middleware('throttle:staff-sensitive');
    Route::patch('/{order:number}/rider', OrderRiderController::class)->name('rider');
    Route::get('/{order:number}/receipt', [OrderReceiptController::class, 'show'])->name('receipt');
    Route::get('/{order:number}/receipt.pdf', [OrderReceiptController::class, 'download'])->name('receipt.pdf');
});
