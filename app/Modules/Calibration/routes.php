<?php

use App\Modules\Calibration\Http\Controllers\CertificateController;
use App\Modules\Calibration\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

/*
 | Calibration module — web routes. Loaded by CalibrationServiceProvider inside
 | the shared [web, auth, verified] group; "access calibration" gates the section.
 */
Route::prefix('calibration')->name('calibration.')->middleware('permission:access calibration')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('certificates')->name('certificates.')->group(function () {
        Route::get('/', [CertificateController::class, 'index'])->name('index');
        Route::post('/', [CertificateController::class, 'store'])->name('store');
        Route::get('/{certificate}', [CertificateController::class, 'show'])->name('show');
        Route::match(['put', 'patch'], '/{certificate}', [CertificateController::class, 'update'])->name('update');
        Route::delete('/{certificate}', [CertificateController::class, 'destroy'])->name('destroy');
    });
});
