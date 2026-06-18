<?php

use App\Modules\Manufacturing\Http\Controllers\BomController;
use App\Modules\Manufacturing\Http\Controllers\DashboardController;
use App\Modules\Manufacturing\Http\Controllers\WorkOrderController;
use Illuminate\Support\Facades\Route;

/*
 | Manufacturing module — web routes. Loaded by ManufacturingServiceProvider
 | inside the shared [web, auth, verified] group; "access manufacturing" gates it.
 */
Route::prefix('manufacturing')->name('manufacturing.')->middleware('permission:access manufacturing')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('boms')->name('boms.')->group(function () {
        Route::get('/', [BomController::class, 'index'])->name('index');
        Route::get('/create', [BomController::class, 'create'])->name('create');
        Route::post('/', [BomController::class, 'store'])->name('store');
        Route::get('/{bom}', [BomController::class, 'show'])->name('show');
        Route::get('/{bom}/edit', [BomController::class, 'edit'])->name('edit');
        Route::match(['put', 'patch'], '/{bom}', [BomController::class, 'update'])->name('update');
        Route::delete('/{bom}', [BomController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('work-orders')->name('work-orders.')->group(function () {
        Route::get('/', [WorkOrderController::class, 'index'])->name('index');
        Route::get('/create', [WorkOrderController::class, 'create'])->name('create');
        Route::post('/', [WorkOrderController::class, 'store'])->name('store');
        Route::get('/{workOrder}', [WorkOrderController::class, 'show'])->name('show');
        Route::match(['put', 'patch'], '/{workOrder}', [WorkOrderController::class, 'update'])->name('update');
        Route::delete('/{workOrder}', [WorkOrderController::class, 'destroy'])->name('destroy');

        Route::post('/{workOrder}/release', [WorkOrderController::class, 'release'])->name('release');
        Route::post('/{workOrder}/start', [WorkOrderController::class, 'start'])->name('start');
        Route::post('/{workOrder}/complete', [WorkOrderController::class, 'complete'])->name('complete');
        Route::post('/{workOrder}/cancel', [WorkOrderController::class, 'cancel'])->name('cancel');
    });
});
