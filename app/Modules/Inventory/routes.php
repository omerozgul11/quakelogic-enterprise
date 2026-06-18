<?php

use App\Modules\Inventory\Http\Controllers\DashboardController;
use App\Modules\Inventory\Http\Controllers\MovementController;
use App\Modules\Inventory\Http\Controllers\ProductController;
use App\Modules\Inventory\Http\Controllers\StockController;
use App\Modules\Inventory\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

/*
 | Inventory & Warehouse module — web routes. Loaded by InventoryServiceProvider
 | inside the shared [web, auth, verified] group; "access inventory" gates the
 | whole section (mirrors the CRM / Shipments sections).
 */
Route::prefix('inventory')->name('inventory.')->middleware('permission:access inventory')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('products')->name('products.')->group(function () {
        Route::get('/', [ProductController::class, 'index'])->name('index');
        Route::post('/', [ProductController::class, 'store'])->name('store');
        Route::get('/{product}', [ProductController::class, 'show'])->name('show');
        Route::match(['put', 'patch'], '/{product}', [ProductController::class, 'update'])->name('update');
        Route::delete('/{product}', [ProductController::class, 'destroy'])->name('destroy');

        // Stock operations (gated per-action by the 'adjust stock' permission).
        Route::post('/{product}/receive', [StockController::class, 'receive'])->name('receive');
        Route::post('/{product}/issue', [StockController::class, 'issue'])->name('issue');
        Route::post('/{product}/adjust', [StockController::class, 'adjust'])->name('adjust');
        Route::post('/{product}/count', [StockController::class, 'count'])->name('count');
        Route::post('/{product}/transfer', [StockController::class, 'transfer'])->name('transfer');
    });

    Route::prefix('warehouses')->name('warehouses.')->group(function () {
        Route::get('/', [WarehouseController::class, 'index'])->name('index');
        Route::post('/', [WarehouseController::class, 'store'])->name('store');
        Route::get('/{warehouse}', [WarehouseController::class, 'show'])->name('show');
        Route::match(['put', 'patch'], '/{warehouse}', [WarehouseController::class, 'update'])->name('update');
        Route::delete('/{warehouse}', [WarehouseController::class, 'destroy'])->name('destroy');

        Route::post('/{warehouse}/locations', [WarehouseController::class, 'storeLocation'])->name('locations.store');
        Route::match(['put', 'patch'], '/{warehouse}/locations/{location}', [WarehouseController::class, 'updateLocation'])->name('locations.update');
        Route::delete('/{warehouse}/locations/{location}', [WarehouseController::class, 'destroyLocation'])->name('locations.destroy');
    });

    Route::get('/movements', [MovementController::class, 'index'])->name('movements.index');
});
