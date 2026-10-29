<?php

use App\Http\Controllers\Staff\Menu\CategoryController;
use App\Http\Controllers\Staff\Menu\ItemController;
use App\Http\Controllers\Staff\Menu\ItemFlagController;
use App\Http\Controllers\Staff\Menu\MenuManagerController;
use Illuminate\Support\Facades\Route;

Route::prefix('menu')->name('menu.')->group(function () {
    Route::get('/', [MenuManagerController::class, 'index'])->name('index');

    Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::patch('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
    Route::post('categories/{category}/move', [CategoryController::class, 'move'])->name('categories.move');

    Route::get('items/create', [ItemController::class, 'create'])->name('items.create');
    Route::post('items', [ItemController::class, 'store'])->name('items.store');
    Route::get('items/{item}/edit', [ItemController::class, 'edit'])->name('items.edit');
    Route::put('items/{item}', [ItemController::class, 'update'])->name('items.update');
    Route::patch('items/{item}/availability', [ItemFlagController::class, 'availability'])->name('items.availability');
    Route::patch('items/{item}/special', [ItemFlagController::class, 'special'])->name('items.special');
});
