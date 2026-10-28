<?php

use App\Http\Controllers\Staff\KitchenController;
use Illuminate\Support\Facades\Route;

Route::prefix('kitchen')->name('kitchen.')->group(function () {
    Route::get('/', [KitchenController::class, 'index'])->name('index');
    Route::post('lines/{line}/bump', [KitchenController::class, 'bumpLine'])->name('lines.bump');
    Route::post('lines/{line}/recall', [KitchenController::class, 'recallLine'])->name('lines.recall');
    Route::post('orders/{order}/bump', [KitchenController::class, 'bumpOrder'])->name('orders.bump');
    Route::post('items/{item}/sold-out', [KitchenController::class, 'soldOut'])->name('items.sold-out');
});
