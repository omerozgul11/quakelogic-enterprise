<?php

use App\Modules\ServiceDesk\Http\Controllers\DashboardController;
use App\Modules\ServiceDesk\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

/*
 | Service Desk module — web routes. Loaded by ServiceDeskServiceProvider inside
 | the shared [web, auth, verified] group; "access tickets" gates the section.
 */
Route::prefix('tickets')->name('tickets.')->middleware('permission:access tickets')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('queue')->name('queue.')->group(function () {
        Route::get('/', [TicketController::class, 'index'])->name('index');
        Route::get('/create', [TicketController::class, 'create'])->name('create');
        Route::post('/', [TicketController::class, 'store'])->name('store');
        Route::get('/{ticket}', [TicketController::class, 'show'])->name('show');
        Route::match(['put', 'patch'], '/{ticket}', [TicketController::class, 'update'])->name('update');
        Route::delete('/{ticket}', [TicketController::class, 'destroy'])->name('destroy');

        Route::post('/{ticket}/comments', [TicketController::class, 'comment'])->name('comments.store');
        Route::post('/{ticket}/assign', [TicketController::class, 'assign'])->name('assign');
        Route::post('/{ticket}/status', [TicketController::class, 'status'])->name('status');
        Route::post('/{ticket}/priority', [TicketController::class, 'priority'])->name('priority');
    });
});
