<?php

use App\Http\Controllers\Staff\Deliveries\AssignController;
use App\Http\Controllers\Staff\Deliveries\DeliverController;
use App\Http\Controllers\Staff\Deliveries\FailController;
use App\Http\Controllers\Staff\Deliveries\PickupController;
use App\Http\Controllers\Staff\DeliveryController;
use Illuminate\Support\Facades\Route;

Route::prefix('deliveries')->name('deliveries.')->group(function () {
    Route::get('/', [DeliveryController::class, 'index'])->name('index');
    Route::post('/{order:number}/pickup', PickupController::class)->name('pickup');
    Route::post('/{order:number}/deliver', DeliverController::class)->name('deliver');
    Route::post('/{order:number}/fail', FailController::class)->name('fail');
    Route::patch('/{order:number}/rider', AssignController::class)->name('rider');
});
