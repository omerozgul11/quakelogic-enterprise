<?php

use App\Modules\ExpenseTracker\Http\Controllers\DashboardController;
use App\Modules\ExpenseTracker\Http\Controllers\ExpenseCategoryController;
use App\Modules\ExpenseTracker\Http\Controllers\ExpenseController;
use App\Modules\ExpenseTracker\Http\Controllers\QuickBooksController;
use App\Modules\ExpenseTracker\Http\Controllers\RecurringExpenseController;
use App\Modules\ExpenseTracker\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

/*
 | Expense Tracker module — web routes. Loaded by ExpenseTrackerServiceProvider
 | inside the shared [web, auth, verified] group; "access expenses" gates the
 | section. The expenses list lives at /expenses/list so the Dashboard (/expenses)
 | and Expenses nav tiles highlight independently.
 */
Route::prefix('expenses')->name('expenses.')->middleware('permission:access expenses')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports');
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');

    // Expenses
    Route::post('/extract', [ExpenseController::class, 'extract'])->name('extract'); // AI receipt → prefill
    Route::get('/list', [ExpenseController::class, 'index'])->name('index');
    Route::post('/list', [ExpenseController::class, 'store'])->name('store');
    Route::get('/list/{expense}', [ExpenseController::class, 'show'])->name('show');
    Route::match(['put', 'patch'], '/list/{expense}', [ExpenseController::class, 'update'])->name('update');
    Route::delete('/list/{expense}', [ExpenseController::class, 'destroy'])->name('destroy');

    Route::post('/list/{expense}/submit', [ExpenseController::class, 'submit'])->name('submit');
    Route::post('/list/{expense}/approve', [ExpenseController::class, 'approve'])->name('approve');
    Route::post('/list/{expense}/reject', [ExpenseController::class, 'reject'])->name('reject');
    Route::post('/list/{expense}/reimburse', [ExpenseController::class, 'reimburse'])->name('reimburse');

    Route::post('/list/{expense}/receipts', [ExpenseController::class, 'storeReceipt'])->name('receipts.store');
    Route::get('/list/{expense}/receipts/{attachment}', [ExpenseController::class, 'downloadReceipt'])->name('receipts.download');
    Route::delete('/list/{expense}/receipts/{attachment}', [ExpenseController::class, 'destroyReceipt'])->name('receipts.destroy');

    // Payments (paid / partially paid / due)
    Route::post('/list/{expense}/payments', [ExpenseController::class, 'storePayment'])->name('payments.store');
    Route::delete('/list/{expense}/payments/{payment}', [ExpenseController::class, 'destroyPayment'])->name('payments.destroy');

    // Categories
    Route::prefix('categories')->name('categories.')->group(function () {
        Route::get('/', [ExpenseCategoryController::class, 'index'])->name('index');
        Route::post('/', [ExpenseCategoryController::class, 'store'])->name('store');
        Route::match(['put', 'patch'], '/{category}', [ExpenseCategoryController::class, 'update'])->name('update');
        Route::delete('/{category}', [ExpenseCategoryController::class, 'destroy'])->name('destroy');
    });

    // QuickBooks Online integration
    Route::prefix('quickbooks')->name('quickbooks.')->group(function () {
        Route::get('/', [QuickBooksController::class, 'index'])->name('index');
        Route::get('/connect', [QuickBooksController::class, 'connect'])->name('connect');
        Route::get('/callback', [QuickBooksController::class, 'callback'])->name('callback');
        Route::post('/sync', [QuickBooksController::class, 'sync'])->name('sync');
        Route::post('/push-toggle', [QuickBooksController::class, 'togglePush'])->name('push-toggle');
        Route::delete('/', [QuickBooksController::class, 'disconnect'])->name('disconnect');
    });

    // Recurring costs
    Route::prefix('recurring')->name('recurring.')->group(function () {
        Route::get('/', [RecurringExpenseController::class, 'index'])->name('index');
        Route::post('/', [RecurringExpenseController::class, 'store'])->name('store');
        Route::match(['put', 'patch'], '/{recurring}', [RecurringExpenseController::class, 'update'])->name('update');
        Route::delete('/{recurring}', [RecurringExpenseController::class, 'destroy'])->name('destroy');
        Route::post('/{recurring}/generate', [RecurringExpenseController::class, 'generateNow'])->name('generate');
    });
});
