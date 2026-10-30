<?php

use App\Http\Controllers\Account\AccountController;
use App\Http\Controllers\Account\AddressController;
use App\Http\Controllers\Account\OrderHistoryController;
use App\Http\Controllers\Account\ReviewController;
use App\Http\Controllers\BasketController;
use App\Http\Controllers\CartLineController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\OrderTrackingController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\StyleguideController;
use App\Http\Controllers\TableOrderingController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::controller(PageController::class)->group(function () {
    Route::get('story', 'story')->name('story');
    Route::get('visit', 'visit')->name('visit');
    Route::get('allergens', 'allergens')->name('allergens');
});

Route::controller(MenuController::class)->prefix('menu')->name('menu.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('{item:slug}', 'show')->name('show');
});

Route::get('basket', BasketController::class)->name('basket');
Route::controller(CartLineController::class)->middleware('throttle:basket')->prefix('basket/lines')->name('basket.lines.')->group(function () {
    Route::post('/', 'store')->name('store');
    Route::patch('{key}', 'update')->name('update');
    Route::delete('{key}', 'destroy')->name('destroy');
});

Route::controller(CheckoutController::class)->prefix('checkout')->name('checkout.')->group(function () {
    Route::get('/', 'show')->name('show');
    Route::post('promo', 'promo')->middleware('throttle:promo')->name('promo');
    Route::post('postcode', 'postcode')->middleware('throttle:checkout')->name('postcode');
    Route::post('/', 'store')->middleware('throttle:checkout')->name('store');
});

Route::get('orders/{order:number}', [OrderTrackingController::class, 'show'])->middleware('throttle:lookup')->name('orders.show');

Route::controller(ReservationController::class)->prefix('book')->name('book.')->group(function () {
    Route::get('/', 'create')->name('create');
    Route::get('slots', 'slots')->name('slots');
    Route::post('/', 'store')->middleware('throttle:booking')->name('store');
    Route::get('{reservation:reference}', 'show')->name('show');
});

Route::controller(TableOrderingController::class)->prefix('t/{table:qr_token}')->name('table.')->group(function () {
    Route::get('/', 'show')->name('show');
    Route::post('requests', 'request')->middleware('throttle:table')->name('request');
    Route::get('bill', 'bill')->name('bill');
    Route::post('order', 'order')->middleware('throttle:table')->name('order');
});

Route::middleware(['auth', 'verified'])->prefix('account')->name('account.')->group(function () {
    Route::get('/', AccountController::class)->name('show');
    Route::get('orders', [OrderHistoryController::class, 'index'])->name('orders.index');
    Route::post('orders/{order:number}/reorder', [OrderHistoryController::class, 'reorder'])->name('orders.reorder');
    Route::resource('addresses', AddressController::class)->except(['show', 'create', 'edit']);
    Route::get('reviews', [ReviewController::class, 'index'])->name('reviews.index');
    Route::post('orders/{order:number}/review', [ReviewController::class, 'store'])->name('reviews.store');
});

Route::controller(StyleguideController::class)->prefix('styleguide')->name('styleguide.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('motion', 'motion')->name('motion');
});

require __DIR__.'/settings.php';
require __DIR__.'/staff.php';
