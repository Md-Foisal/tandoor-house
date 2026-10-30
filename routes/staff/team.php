<?php

use App\Http\Controllers\Staff\Team\AttendanceController;
use App\Http\Controllers\Staff\Team\PeopleController;
use App\Http\Controllers\Staff\Team\ShiftController;
use App\Http\Controllers\Staff\Team\TeamController;
use App\Http\Controllers\Staff\Team\TipsController;
use Illuminate\Support\Facades\Route;

Route::prefix('team')->name('team.')->group(function () {
    Route::get('/', [TeamController::class, 'index'])->name('index');

    Route::post('/people', [PeopleController::class, 'store'])->name('people.store')->middleware('throttle:staff-sensitive');
    Route::post('/people/{user}/invite', [PeopleController::class, 'invite'])->name('people.invite')->middleware('throttle:staff-sensitive');
    Route::patch('/people/{user}', [PeopleController::class, 'update'])->name('people.update');
    Route::delete('/people/{user}', [PeopleController::class, 'destroy'])->name('people.destroy');

    Route::post('/shifts', [ShiftController::class, 'store'])->name('shifts.store');
    Route::post('/shifts/copy-week', [ShiftController::class, 'copy'])->name('shifts.copy');
    Route::patch('/shifts/{shift}', [ShiftController::class, 'update'])->name('shifts.update');
    Route::delete('/shifts/{shift}', [ShiftController::class, 'destroy'])->name('shifts.destroy');

    Route::get('/attendance/export', [AttendanceController::class, 'export'])->name('attendance.export');
    Route::patch('/attendance/{attendance}', [AttendanceController::class, 'fix'])->name('attendance.fix');

    Route::get('/tips/export', [TipsController::class, 'export'])->name('tips.export');
    Route::post('/tips/record', [TipsController::class, 'record'])->name('tips.record')->middleware('throttle:staff-sensitive');
    Route::post('/tips/pay', [TipsController::class, 'pay'])->name('tips.pay')->middleware('throttle:staff-sensitive');
});
