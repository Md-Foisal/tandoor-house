<?php

use App\Enums\StaffArea;
use App\Http\Controllers\Staff\StaffPwaController;
use Illuminate\Support\Facades\Route;

/*
| The staff panel. Each section's routes live in routes/staff/<area>.php and
| are guarded by the matching "staff.<area>" gate.
*/
Route::middleware(['auth', 'verified', 'staff'])->prefix('staff')->name('staff.')->group(function () {
    foreach (StaffArea::cases() as $area) {
        Route::middleware("can:staff.{$area->value}")->group(base_path("routes/staff/{$area->value}.php"));
    }
});

// Public on purpose: browsers fetch these without the session, and neither carries data.
Route::prefix('staff')->name('staff.pwa.')->group(function () {
    Route::get('manifest.webmanifest', [StaffPwaController::class, 'manifest'])->name('manifest');
    Route::get('offline', [StaffPwaController::class, 'offline'])->name('offline');
});
