<?php

use App\Http\Controllers\Api\V1\OpportunityApiController;
use App\Http\Controllers\Api\V1\ProposalApiController;
use App\Http\Controllers\Api\V1\AgencyApiController;
use App\Http\Controllers\Api\V1\CompanyApiController;
use App\Http\Controllers\Api\V1\ContactApiController;
use App\Http\Controllers\Api\V1\CommissionApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->middleware(['auth:sanctum'])->group(function () {

    Route::apiResource('opportunities', OpportunityApiController::class)->except(['create', 'edit']);
    Route::apiResource('proposals', ProposalApiController::class)->except(['create', 'edit']);
    Route::apiResource('agencies', AgencyApiController::class)->except(['create', 'edit']);
    Route::apiResource('companies', CompanyApiController::class)->except(['create', 'edit']);
    Route::apiResource('contacts', ContactApiController::class)->except(['create', 'edit']);
    Route::apiResource('commissions', CommissionApiController::class)->only(['index', 'show']);

    Route::get('/me', fn(\Illuminate\Http\Request $r) => response()->json($r->user()->load('roles')))->name('me');

    Route::get('/health', fn() => response()->json([
        'status' => 'ok',
        'version' => config('app.version', '1.0.0'),
        'timestamp' => now()->toISOString(),
    ]))->name('health');
});
