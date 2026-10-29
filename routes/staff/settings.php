<?php

use App\Http\Controllers\Staff\Settings\AutomationController;
use App\Http\Controllers\Staff\Settings\BankHolidayController;
use App\Http\Controllers\Staff\Settings\DeliveryZoneController;
use App\Http\Controllers\Staff\Settings\HygieneController;
use App\Http\Controllers\Staff\Settings\NotificationController;
use App\Http\Controllers\Staff\Settings\OpeningHoursController;
use App\Http\Controllers\Staff\Settings\ReceiptController;
use App\Http\Controllers\Staff\Settings\SettingsController;
use App\Http\Controllers\Staff\Settings\TaxController;
use Illuminate\Support\Facades\Route;

Route::prefix('settings')->name('settings.')->group(function () {
    Route::get('/', [SettingsController::class, 'index'])->name('index');

    Route::put('hours', [OpeningHoursController::class, 'update'])->name('hours.update');

    Route::put('holidays', [BankHolidayController::class, 'update'])->name('holidays.update');
    Route::post('holidays/next-year', [BankHolidayController::class, 'addNextYear'])->name('holidays.next-year');

    Route::post('zones', [DeliveryZoneController::class, 'store'])->name('zones.store');
    Route::patch('zones/{zone}', [DeliveryZoneController::class, 'update'])->name('zones.update');
    Route::delete('zones/{zone}', [DeliveryZoneController::class, 'destroy'])->name('zones.destroy');

    Route::put('tax', [TaxController::class, 'update'])->name('tax.update');
    Route::put('receipts', [ReceiptController::class, 'update'])->name('receipts.update');
    Route::put('notifications', [NotificationController::class, 'update'])->name('notifications.update');
    Route::put('hygiene', [HygieneController::class, 'update'])->name('hygiene.update');
    Route::put('automations', [AutomationController::class, 'update'])->name('automations.update');
});
