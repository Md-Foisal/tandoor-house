<?php

use App\Http\Controllers\StyleguideController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::controller(StyleguideController::class)->prefix('styleguide')->name('styleguide.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('motion', 'motion')->name('motion');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
