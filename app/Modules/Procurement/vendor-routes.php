<?php

use App\Modules\Procurement\Http\Controllers\Vendor\AuthController;
use App\Modules\Procurement\Http\Controllers\Vendor\PortalController;
use App\Modules\Procurement\Http\Middleware\EnsureVendorAuthenticated;
use App\Modules\Procurement\Http\Middleware\EnsureVendorPortalEnabled;
use Illuminate\Support\Facades\Route;

/*
 | Vendor self-service portal — supplier contacts sign in to a read-only view of
 | their own POs, quotations, and bills. Registered by ProcurementServiceProvider
 | under `web` + prefix `vendor` (NOT the staff auth group). Every route is gated
 | by the feature flag; the inner group additionally requires a vendor session.
 */
Route::middleware(EnsureVendorPortalEnabled::class)->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.attempt');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware(EnsureVendorAuthenticated::class)->group(function () {
        Route::get('/', [PortalController::class, 'dashboard'])->name('dashboard');
        Route::get('documents/{type}/{id}/pdf', [PortalController::class, 'pdf'])
            ->whereIn('type', ['purchase-orders', 'quotations', 'bills'])
            ->whereNumber('id')
            ->name('pdf');
    });
});
