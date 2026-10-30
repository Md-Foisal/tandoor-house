<?php

use App\Http\Controllers\Auth\StaffInviteController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->prefix('invite')->name('invite.')->group(function () {
    Route::get('{token}', [StaffInviteController::class, 'show'])->middleware('throttle:lookup')->name('show');
    Route::post('{token}', [StaffInviteController::class, 'store'])->middleware('throttle:6,1')->name('store');
});
