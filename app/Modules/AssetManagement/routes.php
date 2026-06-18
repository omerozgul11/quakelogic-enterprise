<?php

use App\Modules\AssetManagement\Http\Controllers\AssetController;
use App\Modules\AssetManagement\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

/*
 | Asset Management module — web routes. Loaded by AssetManagementServiceProvider
 | inside the shared [web, auth, verified] group; "access assets" gates the section.
 */
Route::prefix('assets')->name('assets.')->middleware('permission:access assets')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('registry')->name('registry.')->group(function () {
        Route::get('/', [AssetController::class, 'index'])->name('index');
        Route::post('/', [AssetController::class, 'store'])->name('store');
        Route::post('/commission', [AssetController::class, 'commission'])->name('commission');
        Route::get('/{asset}', [AssetController::class, 'show'])->name('show');
        Route::match(['put', 'patch'], '/{asset}', [AssetController::class, 'update'])->name('update');
        Route::delete('/{asset}', [AssetController::class, 'destroy'])->name('destroy');

        Route::post('/{asset}/transition', [AssetController::class, 'transition'])->name('transition');
        Route::post('/{asset}/maintenance', [AssetController::class, 'storeMaintenance'])->name('maintenance.store');
        Route::delete('/{asset}/maintenance/{record}', [AssetController::class, 'destroyMaintenance'])->name('maintenance.destroy');
    });
});
