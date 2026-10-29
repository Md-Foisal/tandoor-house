<?php

use App\Http\Controllers\Staff\ReservationBoardController;
use App\Http\Controllers\Staff\Reservations\ReassignTableController;
use App\Http\Controllers\Staff\Reservations\StaffBookingController;
use App\Http\Controllers\Staff\Reservations\TransitionController;
use Illuminate\Support\Facades\Route;

Route::prefix('reservations')->name('reservations.')->group(function () {
    Route::get('/', [ReservationBoardController::class, 'index'])->name('index');
    Route::post('/', [StaffBookingController::class, 'store'])->name('store');
    Route::post('{reservation}/status', TransitionController::class)->name('status');
    Route::patch('{reservation}/table', ReassignTableController::class)->name('table');
});
