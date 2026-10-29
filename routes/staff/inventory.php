<?php

use App\Http\Controllers\Staff\Inventory\IngredientController;
use App\Http\Controllers\Staff\Inventory\MovementController;
use App\Http\Controllers\Staff\Inventory\PurchaseOrderController;
use App\Http\Controllers\Staff\Inventory\PurchaseOrderPdfController;
use App\Http\Controllers\Staff\Inventory\PurchaseOrderTransitionController;
use App\Http\Controllers\Staff\Inventory\RecipeController;
use App\Http\Controllers\Staff\Inventory\StockController;
use App\Http\Controllers\Staff\Inventory\SupplierController;
use App\Http\Controllers\Staff\Inventory\WasteController;
use Illuminate\Support\Facades\Route;

Route::prefix('inventory')->name('inventory.')->group(function () {
    Route::get('/', [IngredientController::class, 'index'])->name('index');
    Route::post('/ingredients', [IngredientController::class, 'store'])->name('ingredients.store');
    Route::patch('/ingredients/{ingredient}', [IngredientController::class, 'update'])->name('ingredients.update');
    Route::post('/ingredients/{ingredient}/stock-in', [StockController::class, 'stockIn'])->name('ingredients.stock-in');
    Route::post('/ingredients/{ingredient}/adjust', [StockController::class, 'adjust'])->name('ingredients.adjust');

    Route::get('/waste', [WasteController::class, 'index'])->name('waste.index');
    Route::post('/waste', [WasteController::class, 'store'])->name('waste.store');

    Route::get('/movements', [MovementController::class, 'index'])->name('movements.index');

    Route::get('/recipes', [RecipeController::class, 'index'])->name('recipes.index');
    Route::put('/recipes/{menuItem}', [RecipeController::class, 'update'])->name('recipes.update');

    Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
    Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
    Route::patch('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');

    Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
    Route::post('/purchase-orders', [PurchaseOrderController::class, 'store'])->name('purchase-orders.store');
    Route::get('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('purchase-orders.show');
    Route::put('/purchase-orders/{purchaseOrder}/lines', [PurchaseOrderController::class, 'update'])->name('purchase-orders.lines');
    Route::get('/purchase-orders/{purchaseOrder}/pdf', PurchaseOrderPdfController::class)->name('purchase-orders.pdf');
    Route::post('/purchase-orders/{purchaseOrder}/send', [PurchaseOrderTransitionController::class, 'send'])->name('purchase-orders.send');
    Route::post('/purchase-orders/{purchaseOrder}/receive', [PurchaseOrderTransitionController::class, 'receive'])->name('purchase-orders.receive');
    Route::post('/purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderTransitionController::class, 'cancel'])->name('purchase-orders.cancel');
});
