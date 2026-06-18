<?php

use App\Modules\Finance\Http\Controllers\CreditNoteController;
use App\Modules\Finance\Http\Controllers\DashboardController;
use App\Modules\Finance\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

/*
 | Finance / AR module — web routes. Loaded by FinanceServiceProvider inside the
 | shared [web, auth, verified] group; "access finance" gates the section. The
 | invoice routes operate on the existing CRM invoices (App\Models\Crm\Invoice).
 */
Route::prefix('finance')->name('finance.')->middleware('permission:access finance')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('invoices')->name('invoices.')->group(function () {
        Route::get('/', [InvoiceController::class, 'index'])->name('index');
        Route::get('/{invoice}', [InvoiceController::class, 'show'])->name('show');
        Route::post('/{invoice}/collect', [InvoiceController::class, 'collect'])->name('collect');
        Route::post('/{invoice}/record-payment', [InvoiceController::class, 'recordPayment'])->name('record');
        Route::post('/{invoice}/intents/{intent}/capture', [InvoiceController::class, 'capture'])->name('capture');
    });

    Route::prefix('credit-notes')->name('credit-notes.')->group(function () {
        Route::get('/', [CreditNoteController::class, 'index'])->name('index');
        Route::post('/', [CreditNoteController::class, 'store'])->name('store');
        Route::post('/{creditNote}/apply', [CreditNoteController::class, 'apply'])->name('apply');
        Route::post('/{creditNote}/void', [CreditNoteController::class, 'void'])->name('void');
        Route::delete('/{creditNote}', [CreditNoteController::class, 'destroy'])->name('destroy');
    });
});
