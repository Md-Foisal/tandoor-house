<?php

use App\Http\Controllers\Staff\Reports\ReportController;
use App\Http\Controllers\Staff\Reports\ReportCsvController;
use App\Http\Controllers\Staff\Reports\ReportPdfController;
use App\Reports\ReportSections;
use Illuminate\Support\Facades\Route;

Route::prefix('reports')->name('reports.')->group(function () {
    Route::get('/', [ReportController::class, 'index'])->name('index');
    Route::get('/pdf', ReportPdfController::class)->name('pdf');
    Route::get('/export/{section}', ReportCsvController::class)
        ->whereIn('section', ReportSections::KEYS)
        ->name('export');
});
