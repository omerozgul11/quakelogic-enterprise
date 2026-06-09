<?php

use App\Http\Controllers\Web\AiAssistantController;
use App\Http\Controllers\Web\CaptureController;
use App\Http\Controllers\Web\CommissionController;
use App\Http\Controllers\Web\CrmController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DocumentController;
use App\Http\Controllers\Web\FollowUpController;
use App\Http\Controllers\Web\OpportunityController;
use App\Http\Controllers\Web\ProposalController;
use App\Http\Controllers\Web\ReportController;
use App\Http\Controllers\Web\AdminController;
use App\Http\Controllers\Web\SettingsController;
use App\Http\Controllers\Web\IntegrationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Auth routes handled by Fortify
require __DIR__.'/auth.php';

// Authenticated routes
Route::middleware(['auth', 'verified'])->group(function () {

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/executive', [DashboardController::class, 'executive'])->name('dashboard.executive');

    // Opportunities
    Route::prefix('opportunities')->name('opportunities.')->group(function () {
        Route::get('/', [OpportunityController::class, 'index'])->name('index');
        Route::get('/create', [OpportunityController::class, 'create'])->name('create');
        Route::post('/', [OpportunityController::class, 'store'])->name('store');
        Route::get('/{opportunity}', [OpportunityController::class, 'show'])->name('show');
        Route::get('/{opportunity}/edit', [OpportunityController::class, 'edit'])->name('edit');
        Route::put('/{opportunity}', [OpportunityController::class, 'update'])->name('update');
        Route::delete('/{opportunity}', [OpportunityController::class, 'destroy'])->name('destroy');
        Route::post('/import/sam-gov', [OpportunityController::class, 'importSamGov'])->name('import.sam-gov');
    });

    // Proposals
    Route::prefix('proposals')->name('proposals.')->group(function () {
        Route::get('/', [ProposalController::class, 'index'])->name('index');
        Route::get('/create', [ProposalController::class, 'create'])->name('create');
        Route::post('/', [ProposalController::class, 'store'])->name('store');
        Route::get('/{proposalSubmission}', [ProposalController::class, 'show'])->name('show');
        Route::put('/{proposalSubmission}', [ProposalController::class, 'update'])->name('update');
        Route::delete('/{proposalSubmission}', [ProposalController::class, 'destroy'])->name('destroy');
        Route::post('/{proposalSubmission}/transition', [ProposalController::class, 'transition'])->name('transition');
        Route::post('/{proposalSubmission}/files', [DocumentController::class, 'storeProposalFile'])->name('files.store');
        Route::delete('/{proposalSubmission}/files/{file}', [DocumentController::class, 'destroyProposalFile'])->name('files.destroy');
        Route::get('/{proposalSubmission}/files/{file}/download', [DocumentController::class, 'downloadProposalFile'])->name('files.download');
    });

    // Capture
    Route::prefix('capture')->name('capture.')->group(function () {
        Route::get('/', [CaptureController::class, 'index'])->name('index');
        Route::post('/', [CaptureController::class, 'store'])->name('store');
        Route::get('/{capturePlan}', [CaptureController::class, 'show'])->name('show');
        Route::put('/{capturePlan}', [CaptureController::class, 'update'])->name('update');
        Route::post('/{capturePlan}/transition', [CaptureController::class, 'transition'])->name('transition');
    });

    // Documents
    Route::prefix('documents')->name('documents.')->group(function () {
        Route::get('/', [DocumentController::class, 'index'])->name('index');
    });

    // CRM - Agencies
    Route::prefix('agencies')->name('agencies.')->group(function () {
        Route::get('/', [CrmController::class, 'agenciesIndex'])->name('index');
        Route::post('/', [CrmController::class, 'agencyStore'])->name('store');
        Route::get('/{agency}', [CrmController::class, 'agencyShow'])->name('show');
        Route::put('/{agency}', [CrmController::class, 'agencyUpdate'])->name('update');
    });

    // CRM - Companies
    Route::prefix('companies')->name('companies.')->group(function () {
        Route::get('/', [CrmController::class, 'companiesIndex'])->name('index');
        Route::post('/', [CrmController::class, 'companyStore'])->name('store');
        Route::get('/{company}', [CrmController::class, 'companyShow'])->name('show');
    });

    // CRM - Contacts
    Route::prefix('contacts')->name('contacts.')->group(function () {
        Route::get('/', [CrmController::class, 'contactsIndex'])->name('index');
        Route::post('/', [CrmController::class, 'contactStore'])->name('store');
        Route::get('/{contact}', [CrmController::class, 'contactShow'])->name('show');
    });

    // Follow-ups
    Route::prefix('follow-ups')->name('follow-ups.')->group(function () {
        Route::get('/', [FollowUpController::class, 'index'])->name('index');
        Route::post('/', [FollowUpController::class, 'store'])->name('store');
        Route::put('/{followUp}', [FollowUpController::class, 'update'])->name('update');
    });

    // Commissions
    Route::prefix('commissions')->name('commissions.')->group(function () {
        Route::get('/', [CommissionController::class, 'index'])->name('index');
        Route::get('/rules', [CommissionController::class, 'rules'])->name('rules');
        Route::post('/rules', [CommissionController::class, 'storeRule'])->name('rules.store');
        Route::post('/{commission}/approve', [CommissionController::class, 'approve'])->name('approve');
    });

    // Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::post('/export', [ReportController::class, 'export'])->name('export');
    });

    // AI Assistant
    Route::prefix('ai')->name('ai.')->group(function () {
        Route::get('/', [AiAssistantController::class, 'index'])->name('index');
        Route::post('/analyze', [AiAssistantController::class, 'analyze'])->name('analyze');
        Route::get('/{aiAnalysis}', [AiAssistantController::class, 'show'])->name('show');
        Route::post('/{aiAnalysis}/review', [AiAssistantController::class, 'review'])->name('review');
    });

    // Integrations
    Route::prefix('integrations')->name('integrations.')->group(function () {
        Route::get('/', [IntegrationController::class, 'index'])->name('index');
        Route::post('/sync/{type}', [IntegrationController::class, 'sync'])->name('sync');
    });

    // Admin
    Route::prefix('admin')->name('admin.')->middleware('role:Super Admin')->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('index');
        Route::get('/users', [AdminController::class, 'users'])->name('users');
        Route::post('/users', [AdminController::class, 'storeUser'])->name('users.store');
        Route::match(['put', 'patch'], '/users/{user}', [AdminController::class, 'updateUser'])->name('users.update');
        Route::delete('/users/{user}', [AdminController::class, 'deleteUser'])->name('users.delete');
        Route::get('/audit-logs', [AdminController::class, 'auditLogs'])->name('audit-logs');
    });

    // Settings
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [SettingsController::class, 'index'])->name('index');
        Route::put('/profile', [SettingsController::class, 'updateProfile'])->name('profile');
        Route::put('/password', [SettingsController::class, 'updatePassword'])->name('password');
    });
});
