<?php

use App\Modules\Procurement\Http\Controllers\DashboardController;
use App\Modules\Procurement\Http\Controllers\PurchaseOrderController;
use App\Modules\Procurement\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

/*
 | Procurement module — web routes. Loaded by ProcurementServiceProvider inside
 | the shared [web, auth, verified] group; "access procurement" gates the section.
 */
Route::prefix('procurement')->name('procurement.')->middleware('permission:access procurement')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('suppliers')->name('suppliers.')->group(function () {
        Route::get('/', [SupplierController::class, 'index'])->name('index');
        Route::post('/', [SupplierController::class, 'store'])->name('store');
        Route::get('/{supplier}', [SupplierController::class, 'show'])->name('show');
        Route::match(['put', 'patch'], '/{supplier}', [SupplierController::class, 'update'])->name('update');
        Route::delete('/{supplier}', [SupplierController::class, 'destroy'])->name('destroy');

        Route::post('/{supplier}/contacts', [SupplierController::class, 'storeContact'])->name('contacts.store');
        Route::match(['put', 'patch'], '/{supplier}/contacts/{contact}', [SupplierController::class, 'updateContact'])->name('contacts.update');
        Route::delete('/{supplier}/contacts/{contact}', [SupplierController::class, 'destroyContact'])->name('contacts.destroy');
    });

    Route::prefix('purchase-orders')->name('purchase-orders.')->group(function () {
        Route::get('/', [PurchaseOrderController::class, 'index'])->name('index');
        Route::get('/create', [PurchaseOrderController::class, 'create'])->name('create');
        Route::post('/', [PurchaseOrderController::class, 'store'])->name('store');
        Route::get('/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('show');
        Route::match(['put', 'patch'], '/{purchaseOrder}', [PurchaseOrderController::class, 'update'])->name('update');
        Route::delete('/{purchaseOrder}', [PurchaseOrderController::class, 'destroy'])->name('destroy');

        Route::post('/{purchaseOrder}/submit', [PurchaseOrderController::class, 'submit'])->name('submit');
        Route::post('/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])->name('approve');
        Route::post('/{purchaseOrder}/sent', [PurchaseOrderController::class, 'markSent'])->name('sent');
        Route::post('/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->name('cancel');
        Route::post('/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])->name('receive');
    });
});
