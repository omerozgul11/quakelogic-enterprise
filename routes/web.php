<?php

use App\Http\Controllers\Web\AiAssistantController;
use App\Http\Controllers\Web\CalendarController;
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
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\SearchController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Auth routes handled by Fortify
require __DIR__.'/auth.php';

// Public legal / terms / copyright page (linked from the footer).
Route::get('/legal', fn () => Inertia::render('Legal/Index'))->name('legal');

// Authenticated routes
Route::middleware(['auth', 'verified'])->group(function () {

    // Global search (JSON)
    Route::get('/search', SearchController::class)->name('search');

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/executive', [DashboardController::class, 'executive'])->name('dashboard.executive');

    // Calendar (auto-populated from proposals + opportunities)
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar');

    // Shipments — UPS tracking for mailed proposals (gated by `access shipments`).
    Route::prefix('shipments')->name('shipments.')->middleware('permission:access shipments')->group(function () {
        Route::get('/', [\App\Http\Controllers\Web\MailingController::class, 'dashboard'])->name('dashboard');
        Route::get('/carriers', [\App\Http\Controllers\Web\CarriersController::class, 'index'])->name('carriers');
        Route::prefix('mailings')->name('mailings.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Web\MailingController::class, 'index'])->name('index');
            Route::get('/create', [\App\Http\Controllers\Web\MailingController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\Web\MailingController::class, 'store'])->name('store');
            Route::get('/bulk', [\App\Http\Controllers\Web\MailingController::class, 'bulkCreate'])->name('bulk');
            Route::post('/bulk', [\App\Http\Controllers\Web\MailingController::class, 'bulkStore'])->name('bulk.store');
            Route::get('/{ulid}', [\App\Http\Controllers\Web\MailingController::class, 'show'])->name('show');
            Route::post('/{ulid}/refresh', [\App\Http\Controllers\Web\MailingController::class, 'refresh'])->name('refresh');
        });
    });

    // Shipments admin — admins only. Controls per-user Shipments access
    // (independent of roles + with no effect on Proposals access). Gated on the
    // admin role, NOT on `access shipments`, so admins can always manage it.
    Route::prefix('shipments/admin')->name('shipments.admin.')->middleware('role:Super Admin')->group(function () {
        Route::get('/', [\App\Http\Controllers\Web\ShipmentAccessController::class, 'index'])->name('index');
        Route::match(['put', 'patch', 'post'], '/users/{user}', [\App\Http\Controllers\Web\ShipmentAccessController::class, 'update'])->name('users.update');
    });

    // Opportunities
    Route::prefix('opportunities')->name('opportunities.')->group(function () {
        Route::get('/', [OpportunityController::class, 'index'])->name('index');
        Route::get('/create', [OpportunityController::class, 'create'])->name('create');
        Route::post('/', [OpportunityController::class, 'store'])->name('store');
        // Per-user keyword filters (registered before the {opportunity} wildcard).
        Route::post('/keywords', [OpportunityController::class, 'storeKeyword'])->name('keywords.store');
        Route::delete('/keywords', [OpportunityController::class, 'destroyKeyword'])->name('keywords.destroy');
        Route::get('/{opportunity}', [OpportunityController::class, 'show'])->name('show');
        Route::get('/{opportunity}/edit', [OpportunityController::class, 'edit'])->name('edit');
        Route::put('/{opportunity}', [OpportunityController::class, 'update'])->name('update');
        Route::delete('/{opportunity}', [OpportunityController::class, 'destroy'])->name('destroy');
        Route::post('/{opportunity}/pursue', [OpportunityController::class, 'pursue'])->name('pursue');
        Route::post('/{opportunity}/save', [OpportunityController::class, 'toggleSave'])->name('save');
        // Solicitation documents pulled live from the SAM.gov record.
        Route::get('/{opportunity}/documents/{index}', [OpportunityController::class, 'document'])->whereNumber('index')->name('documents.show');
        Route::post('/import/sam-gov', [OpportunityController::class, 'importSamGov'])->name('import.sam-gov');
    });

    // Proposals
    Route::prefix('proposals')->name('proposals.')->group(function () {
        Route::get('/', [ProposalController::class, 'index'])->name('index');
        Route::get('/create', [ProposalController::class, 'create'])->name('create');
        Route::get('/board', [ProposalController::class, 'board'])->name('board');
        Route::post('/', [ProposalController::class, 'store'])->name('store');
        Route::post('/intake', [ProposalController::class, 'intake'])->name('intake');
        Route::get('/{proposalSubmission}', [ProposalController::class, 'show'])->name('show');
        Route::get('/{proposalSubmission}/edit', [ProposalController::class, 'edit'])->name('edit');
        Route::get('/{proposalSubmission}/review', [ProposalController::class, 'review'])->name('review');
        Route::post('/{proposalSubmission}/review', [ProposalController::class, 'applyExtraction'])->name('review.apply');
        Route::put('/{proposalSubmission}', [ProposalController::class, 'update'])->name('update');
        Route::delete('/{proposalSubmission}', [ProposalController::class, 'destroy'])->name('destroy');
        Route::post('/{proposalSubmission}/transition', [ProposalController::class, 'transition'])->name('transition');
        Route::post('/{proposalSubmission}/move', [ProposalController::class, 'move'])->name('move');
        Route::post('/{proposalSubmission}/files', [DocumentController::class, 'storeProposalFile'])->name('files.store');
        Route::delete('/{proposalSubmission}/files/{file}', [DocumentController::class, 'destroyProposalFile'])->name('files.destroy');
        Route::get('/{proposalSubmission}/files/{file}/download', [DocumentController::class, 'downloadProposalFile'])->name('files.download');
        Route::get('/{proposalSubmission}/files/{file}/preview', [DocumentController::class, 'previewProposalFile'])->name('files.preview');
        // Solicitation documents pulled live from the linked SAM.gov opportunity.
        Route::get('/{proposalSubmission}/sam-documents/{index}', [DocumentController::class, 'samDocument'])->whereNumber('index')->name('sam-documents.show');
        Route::post('/{proposalSubmission}/sam-documents/{index}/extract', [DocumentController::class, 'extractSamDocument'])->whereNumber('index')->name('sam-documents.extract');
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
        Route::match(['put', 'patch'], '/{company}', [CrmController::class, 'companyUpdate'])->name('update');
        Route::delete('/{company}', [CrmController::class, 'companyDestroy'])->name('destroy');
    });

    // CRM - Contacts
    Route::prefix('contacts')->name('contacts.')->group(function () {
        Route::get('/', [CrmController::class, 'contactsIndex'])->name('index');
        Route::post('/', [CrmController::class, 'contactStore'])->name('store');
        Route::get('/{contact}', [CrmController::class, 'contactShow'])->name('show');
        Route::match(['put', 'patch'], '/{contact}', [CrmController::class, 'contactUpdate'])->name('update');
        Route::delete('/{contact}', [CrmController::class, 'contactDestroy'])->name('destroy');
    });

    // Follow-ups
    Route::prefix('follow-ups')->name('follow-ups.')->group(function () {
        Route::get('/', [FollowUpController::class, 'index'])->name('index');
        Route::post('/', [FollowUpController::class, 'store'])->name('store');
        Route::get('/{followUp}', [FollowUpController::class, 'show'])->name('show');
        Route::match(['put', 'patch'], '/{followUp}', [FollowUpController::class, 'update'])->name('update');
        Route::delete('/{followUp}', [FollowUpController::class, 'destroy'])->name('destroy');
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
        Route::get('/download/{format}', [ReportController::class, 'indexExport'])->name('download');
        Route::get('/users', [ReportController::class, 'users'])->name('users');
        Route::get('/users/download/{format}', [ReportController::class, 'usersExport'])->name('users.download');
        Route::post('/export', [ReportController::class, 'export'])->name('export');
    });

    // User guide (static documentation, available to every signed-in user)
    Route::get('/guide', fn () => \Inertia\Inertia::render('Guide/Index'))->name('guide');

    // Market pricing (past awarded contracts from SAM.gov)
    Route::get('/market-pricing', [\App\Http\Controllers\Web\MarketPricingController::class, 'index'])->name('market-pricing');
    Route::post('/market-pricing/keywords', [\App\Http\Controllers\Web\MarketPricingController::class, 'storeKeyword'])->name('market-pricing.keywords.store');
    Route::delete('/market-pricing/keywords', [\App\Http\Controllers\Web\MarketPricingController::class, 'destroyKeyword'])->name('market-pricing.keywords.destroy');

    // AI Assistant
    Route::prefix('ai')->name('ai.')->group(function () {
        Route::get('/', [AiAssistantController::class, 'index'])->name('index');
        Route::post('/chat', [AiAssistantController::class, 'chat'])->name('chat');
        Route::post('/analyze', [AiAssistantController::class, 'analyze'])->name('analyze');
        Route::get('/{aiAnalysis}', [AiAssistantController::class, 'show'])->name('show');
        Route::post('/{aiAnalysis}/review', [AiAssistantController::class, 'review'])->name('review');
    });

    // Notifications
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::get('/feed', [NotificationController::class, 'feed'])->name('feed');
        Route::post('/read-all', [NotificationController::class, 'markAllRead'])->name('read-all');
        Route::post('/{id}/read', [NotificationController::class, 'markRead'])->name('read');
        Route::delete('/{id}', [NotificationController::class, 'destroy'])->name('destroy');
    });

    // Integrations
    Route::prefix('integrations')->name('integrations.')->group(function () {
        Route::get('/', [IntegrationController::class, 'index'])->name('index');
        Route::post('/sync/{type}', [IntegrationController::class, 'sync'])->name('sync');
    });

    // Admin
    Route::prefix('admin')->name('admin.')->middleware('role:Super Admin')->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('index');
        Route::get('/team', [AdminController::class, 'team'])->name('team');
        Route::get('/activity', [AdminController::class, 'activity'])->name('activity');
        Route::get('/users', [AdminController::class, 'users'])->name('users');
        Route::post('/users', [AdminController::class, 'storeUser'])->name('users.store');
        Route::match(['put', 'patch'], '/users/{user}', [AdminController::class, 'updateUser'])->name('users.update');
        Route::delete('/users/{user}', [AdminController::class, 'deleteUser'])->name('users.delete');
        // Admin-managed work email (SMTP) for a teammate.
        Route::match(['put', 'patch', 'post'], '/users/{user}/mailbox', [AdminController::class, 'connectUserMailbox'])->name('users.mailbox');
        Route::post('/users/{user}/mailbox/test', [AdminController::class, 'testUserMailbox'])->name('users.mailbox.test');
        Route::delete('/users/{user}/mailbox', [AdminController::class, 'disconnectUserMailbox'])->name('users.mailbox.disconnect');
        Route::get('/audit-logs', [AdminController::class, 'auditLogs'])->name('audit-logs');
    });

    // Settings
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [SettingsController::class, 'index'])->name('index');
        // Settings mutations accept PUT/PATCH/POST so a method mismatch (or a
        // stale cached bundle) can never 405 the user on a save.
        Route::match(['put', 'patch', 'post'], '/profile', [SettingsController::class, 'updateProfile'])->name('profile');
        Route::match(['put', 'patch', 'post'], '/password', [SettingsController::class, 'updatePassword'])->name('password');
        Route::match(['put', 'patch', 'post'], '/preferences', [SettingsController::class, 'updatePreferences'])->name('preferences');
        // Per-user work email (SMTP) connection.
        Route::match(['put', 'patch', 'post'], '/mailbox', [SettingsController::class, 'connectMailbox'])->name('mailbox');
        Route::post('/mailbox/test', [SettingsController::class, 'testMailbox'])->name('mailbox.test');
        Route::delete('/mailbox', [SettingsController::class, 'disconnectMailbox'])->name('mailbox.disconnect');
    });
});
