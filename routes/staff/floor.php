<?php

use App\Http\Controllers\Staff\Floor\MergeController;
use App\Http\Controllers\Staff\Floor\RequestController;
use App\Http\Controllers\Staff\Floor\ReservationController;
use App\Http\Controllers\Staff\Floor\TableController;
use App\Http\Controllers\Staff\FloorController;
use Illuminate\Support\Facades\Route;

Route::prefix('floor')->name('floor.')->group(function () {
    Route::get('/', FloorController::class)->name('index');
    Route::post('tables/{table}/status', [TableController::class, 'status'])->name('tables.status');
    Route::post('tables/{table}/transfer', [TableController::class, 'transfer'])->name('tables.transfer');
    Route::post('merge', [MergeController::class, 'store'])->name('merge');
    Route::delete('tables/{table}/merge', [MergeController::class, 'destroy'])->name('unmerge');
    Route::post('requests/{tableRequest}/handle', RequestController::class)->name('requests.handle');
    Route::post('reservations/{reservation}/seat', ReservationController::class)->name('reservations.seat');
});
