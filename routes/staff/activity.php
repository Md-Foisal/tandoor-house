<?php

use App\Http\Controllers\Staff\ActivityLogController;
use Illuminate\Support\Facades\Route;

Route::prefix('activity')->name('activity.')->group(function () {
    Route::get('/', [ActivityLogController::class, 'index'])->name('index');
    Route::get('export', [ActivityLogController::class, 'export'])->name('export');
});
