<?php

use App\Http\Controllers\Staff\Today\ClockController;
use App\Http\Controllers\Staff\TodayController;
use Illuminate\Support\Facades\Route;

Route::get('/', TodayController::class)->name('today');
Route::post('clock-in', [ClockController::class, 'in'])->name('today.clock_in');
Route::post('clock-out', [ClockController::class, 'out'])->name('today.clock_out');
