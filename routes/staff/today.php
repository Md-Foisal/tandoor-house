<?php

use App\Http\Controllers\Staff\TodayController;
use Illuminate\Support\Facades\Route;

Route::get('/', TodayController::class)->name('today');
