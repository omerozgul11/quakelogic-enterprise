<?php

use App\Http\Controllers\Web\AiAssistantController;
use App\Http\Controllers\Web\CalendarController;
use App\Http\Controllers\Web\CommissionController;
use App\Http\Controllers\Web\ComplianceController;
use App\Http\Controllers\Web\ContractController;
use App\Http\Controllers\Web\TemplateController;
use App\Http\Controllers\Web\CrmController;
use App\Http\Controllers\Web\Crm\ClientController as CrmClientController;
use App\Http\Controllers\Web\Crm\ContactController as CrmContactController;
use App\Http\Controllers\Web\Crm\DashboardController as CrmDashboardController;
use App\Http\Controllers\Web\Crm\InvoiceController as CrmInvoiceController;
use App\Http\Controllers\Web\Crm\LeadController as CrmLeadController;
use App\Http\Controllers\Web\Crm\ProjectController as CrmProjectController;
use App\Http\Controllers\Web\Crm\ProjectSettingsController as CrmProjectSettingsController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DocumentController;
use App\Http\Controllers\Web\FollowUpController;
use App\Http\Controllers\Web\OpportunityController;
use App\Http\Controllers\Web\OpportunityOversightController;
use App\Http\Controllers\Web\ProposalController;
use App\Http\Controllers\Web\ProposalCostController;
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
    // Opportunity Command Center — executive oversight of the whole pipeline.
    Route::get('/dashboard/opportunities', [OpportunityOversightController::class, 'index'])->name('dashboard.opportunities');

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
            Route::get('/import', [\App\Http\Controllers\Web\MailingController::class, 'importCreate'])->name('import');
            Route::post('/import', [\App\Http\Controllers\Web\MailingController::class, 'importStore'])->name('import.store');
            Route::post('/refresh-all', [\App\Http\Controllers\Web\MailingController::class, 'refreshAll'])->name('refresh-all');
            Route::post('/match-proposals', [\App\Http\Controllers\Web\MailingController::class, 'matchProposals'])->name('match-proposals');
            Route::get('/{ulid}', [\App\Http\Controllers\Web\MailingController::class, 'show'])->name('show');
            Route::match(['put', 'patch'], '/{ulid}', [\App\Http\Controllers\Web\MailingController::class, 'update'])->name('update');
            Route::post('/{ulid}/refresh', [\App\Http\Controllers\Web\MailingController::class, 'refresh'])->name('refresh');

            // Attached documents (label, customs, receipts — pdf/png/jpeg).
            Route::post('/{ulid}/documents', [\App\Http\Controllers\Web\MailingDocumentController::class, 'store'])->name('documents.store');
            Route::get('/{ulid}/documents/{document}/download', [\App\Http\Controllers\Web\MailingDocumentController::class, 'download'])->name('documents.download');
            Route::get('/{ulid}/documents/{document}/preview', [\App\Http\Controllers\Web\MailingDocumentController::class, 'preview'])->name('documents.preview');
            Route::delete('/{ulid}/documents/{document}', [\App\Http\Controllers\Web\MailingDocumentController::class, 'destroy'])->name('documents.destroy');
        });
    });

    // Shipments admin — admins only. Controls per-user Shipments access
    // (independent of roles + with no effect on Proposals access). Gated on the
    // admin role, NOT on `access shipments`, so admins can always manage it.
    Route::prefix('shipments/admin')->name('shipments.admin.')->middleware('role:Super Admin')->group(function () {
        Route::get('/', [\App\Http\Controllers\Web\ShipmentAccessController::class, 'index'])->name('index');
        Route::match(['put', 'patch', 'post'], '/users/{user}', [\App\Http\Controllers\Web\ShipmentAccessController::class, 'update'])->name('users.update');
    });

    // CRM — dedicated section at /crm (clients, contacts, leads, projects,
    // invoices). Gated by `access crm`, which every role has. Reuses the shared
    // companies/contacts tables; everything else lives in crm_* tables.
    Route::prefix('crm')->name('crm.')->middleware('permission:access crm')->group(function () {
        Route::get('/', [CrmDashboardController::class, 'index'])->name('dashboard');

        // Clients (companies)
        Route::prefix('clients')->name('clients.')->group(function () {
            Route::get('/', [CrmClientController::class, 'index'])->name('index');
            Route::post('/', [CrmClientController::class, 'store'])->name('store');
            Route::get('/{company}', [CrmClientController::class, 'show'])->name('show');
            Route::match(['put', 'patch'], '/{company}', [CrmClientController::class, 'update'])->name('update');
            Route::delete('/{company}', [CrmClientController::class, 'destroy'])->name('destroy');
        });

        // Contacts
        Route::prefix('contacts')->name('contacts.')->group(function () {
            Route::get('/', [CrmContactController::class, 'index'])->name('index');
            Route::post('/', [CrmContactController::class, 'store'])->name('store');
            Route::match(['put', 'patch'], '/{contact}', [CrmContactController::class, 'update'])->name('update');
            Route::delete('/{contact}', [CrmContactController::class, 'destroy'])->name('destroy');
        });

        // Leads & sales pipeline
        Route::prefix('leads')->name('leads.')->group(function () {
            Route::get('/', [CrmLeadController::class, 'index'])->name('index');
            Route::post('/', [CrmLeadController::class, 'store'])->name('store');
            Route::match(['put', 'patch'], '/{lead}', [CrmLeadController::class, 'update'])->name('update');
            Route::post('/{lead}/status', [CrmLeadController::class, 'updateStatus'])->name('status');
            Route::post('/{lead}/convert', [CrmLeadController::class, 'convert'])->name('convert');
            Route::delete('/{lead}', [CrmLeadController::class, 'destroy'])->name('destroy');
        });

        // Projects & tasks — the Project Management workspace.
        Route::prefix('projects')->name('projects.')->group(function () {
            Route::get('/', [CrmProjectController::class, 'index'])->name('index');
            Route::post('/', [CrmProjectController::class, 'store'])->name('store');

            // Admin settings (registered before the {project} wildcard).
            Route::get('/settings', [CrmProjectSettingsController::class, 'edit'])->name('settings');
            Route::match(['put', 'patch'], '/settings', [CrmProjectSettingsController::class, 'update'])->name('settings.update');

            Route::get('/{project}', [CrmProjectController::class, 'show'])->name('show');
            Route::match(['put', 'patch'], '/{project}', [CrmProjectController::class, 'update'])->name('update');
            Route::delete('/{project}', [CrmProjectController::class, 'destroy'])->name('destroy');

            // Tasks + comments
            Route::post('/{project}/tasks', [CrmProjectController::class, 'storeTask'])->name('tasks.store');
            Route::match(['put', 'patch'], '/{project}/tasks/{task}', [CrmProjectController::class, 'updateTask'])->name('tasks.update');
            Route::delete('/{project}/tasks/{task}', [CrmProjectController::class, 'destroyTask'])->name('tasks.destroy');
            Route::post('/{project}/tasks/{task}/comments', [CrmProjectController::class, 'storeTaskComment'])->name('tasks.comments.store');

            // Team members
            Route::post('/{project}/members', [CrmProjectController::class, 'storeMember'])->name('members.store');
            Route::match(['put', 'patch'], '/{project}/members/{member}', [CrmProjectController::class, 'updateMember'])->name('members.update');
            Route::delete('/{project}/members/{member}', [CrmProjectController::class, 'destroyMember'])->name('members.destroy');

            // Milestones
            Route::post('/{project}/milestones', [CrmProjectController::class, 'storeMilestone'])->name('milestones.store');
            Route::match(['put', 'patch'], '/{project}/milestones/{milestone}', [CrmProjectController::class, 'updateMilestone'])->name('milestones.update');
            Route::delete('/{project}/milestones/{milestone}', [CrmProjectController::class, 'destroyMilestone'])->name('milestones.destroy');

            // Notes
            Route::post('/{project}/notes', [CrmProjectController::class, 'storeNote'])->name('notes.store');
            Route::delete('/{project}/notes/{note}', [CrmProjectController::class, 'destroyNote'])->name('notes.destroy');

            // Files
            Route::post('/{project}/files', [CrmProjectController::class, 'storeFile'])->name('files.store');
            Route::get('/{project}/files/{file}/download', [CrmProjectController::class, 'downloadFile'])->name('files.download');
            Route::delete('/{project}/files/{file}', [CrmProjectController::class, 'destroyFile'])->name('files.destroy');
        });

        // Estimates, invoices & payments
        Route::prefix('invoices')->name('invoices.')->group(function () {
            Route::get('/', [CrmInvoiceController::class, 'index'])->name('index');
            Route::get('/create', [CrmInvoiceController::class, 'create'])->name('create');
            Route::post('/', [CrmInvoiceController::class, 'store'])->name('store');
            Route::get('/{invoice}', [CrmInvoiceController::class, 'show'])->name('show');
            Route::get('/{invoice}/edit', [CrmInvoiceController::class, 'edit'])->name('edit');
            Route::match(['put', 'patch'], '/{invoice}', [CrmInvoiceController::class, 'update'])->name('update');
            Route::delete('/{invoice}', [CrmInvoiceController::class, 'destroy'])->name('destroy');
            Route::post('/{invoice}/status', [CrmInvoiceController::class, 'updateStatus'])->name('status');
            Route::post('/{invoice}/payments', [CrmInvoiceController::class, 'storePayment'])->name('payments.store');
            Route::delete('/{invoice}/payments/{payment}', [CrmInvoiceController::class, 'destroyPayment'])->name('payments.destroy');
        });
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
        // Assignment lifecycle: claim/lock, react, (re)assign, stage, release.
        Route::post('/{opportunity}/claim', [OpportunityController::class, 'claim'])->name('claim');
        Route::post('/{opportunity}/react', [OpportunityController::class, 'react'])->name('react');
        Route::post('/{opportunity}/assign', [OpportunityController::class, 'assign'])->name('assign');
        Route::post('/{opportunity}/stage', [OpportunityController::class, 'advanceStage'])->name('stage');
        Route::post('/{opportunity}/release', [OpportunityController::class, 'release'])->name('release');
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
        // Proposal Writer: create a proposal from dumped docs, then auto-draft it.
        Route::post('/intake-draft', [ProposalController::class, 'intakeDraft'])->name('intake-draft');
        Route::get('/{proposalSubmission}', [ProposalController::class, 'show'])->name('show');
        Route::get('/{proposalSubmission}/edit', [ProposalController::class, 'edit'])->name('edit');
        Route::get('/{proposalSubmission}/review', [ProposalController::class, 'review'])->name('review');
        Route::post('/{proposalSubmission}/review', [ProposalController::class, 'applyExtraction'])->name('review.apply');
        Route::put('/{proposalSubmission}', [ProposalController::class, 'update'])->name('update');
        Route::delete('/{proposalSubmission}', [ProposalController::class, 'destroy'])->name('destroy');
        Route::post('/{proposalSubmission}/transition', [ProposalController::class, 'transition'])->name('transition');
        Route::post('/{proposalSubmission}/move', [ProposalController::class, 'move'])->name('move');
        Route::post('/{proposalSubmission}/log-contact', [ProposalController::class, 'logContact'])->name('log-contact');
        // Shipments two-way link (attach / detach a shipment from the proposal page).
        Route::post('/{proposalSubmission}/link-shipment', [ProposalController::class, 'linkShipment'])->name('link-shipment');
        Route::post('/{proposalSubmission}/unlink-shipment', [ProposalController::class, 'unlinkShipment'])->name('unlink-shipment');
        // Phase 5: the contract / financial record attached to this proposal.
        Route::post('/{proposalSubmission}/contract', [ContractController::class, 'upsert'])->name('contract.upsert');
        // Cost line items → quick profit-margin estimate (bid vs. cost).
        Route::post('/{proposalSubmission}/costs', [ProposalCostController::class, 'store'])->name('costs.store');
        Route::patch('/{proposalSubmission}/costs/{cost}', [ProposalCostController::class, 'update'])->name('costs.update');
        Route::delete('/{proposalSubmission}/costs/{cost}', [ProposalCostController::class, 'destroy'])->name('costs.destroy');
        // Phase 18: AI-drafted follow-up email into the proposal thread.
        Route::post('/{proposalSubmission}/draft-follow-up', [ProposalController::class, 'draftFollowUp'])->name('draft-follow-up');
        // Proposal Writer: clarifying questions, then AI-draft a section (JSON).
        Route::post('/{proposalSubmission}/draft-section/questions', [ProposalController::class, 'draftSectionQuestions'])->name('draft-section.questions');
        Route::post('/{proposalSubmission}/draft-section', [ProposalController::class, 'draftSection'])->name('draft-section');
        // Persisted proposal sections + Word/PDF export.
        Route::post('/{proposalSubmission}/sections', [ProposalController::class, 'saveSection'])->name('sections.save');
        Route::delete('/{proposalSubmission}/sections/{section}', [ProposalController::class, 'deleteSection'])->name('sections.delete');
        Route::get('/{proposalSubmission}/export/{format}', [ProposalController::class, 'exportDocument'])->whereIn('format', ['docx', 'pdf'])->name('export');
        // Phase 19: loss analysis + AI loss assessment.
        Route::post('/{proposalSubmission}/loss-analysis', [ProposalController::class, 'lossAnalysis'])->name('loss-analysis');
        Route::post('/{proposalSubmission}/loss-assessment', [ProposalController::class, 'generateLossAssessment'])->name('loss-assessment');
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

    // Contracts (Phase 5 — post-award financial lifecycle)
    Route::prefix('contracts')->name('contracts.')->group(function () {
        Route::get('/', [ContractController::class, 'index'])->middleware('can:view contracts')->name('index');
        Route::post('/{contract}/milestones', [ContractController::class, 'storeMilestone'])->name('milestones.store');
        Route::match(['put', 'patch'], '/{contract}/milestones/{milestone}', [ContractController::class, 'updateMilestone'])->name('milestones.update');
        Route::delete('/{contract}/milestones/{milestone}', [ContractController::class, 'destroyMilestone'])->name('milestones.destroy');
    });

    // Compliance register (Phase 7)
    Route::prefix('compliance')->name('compliance.')->group(function () {
        Route::get('/', [ComplianceController::class, 'index'])->middleware('can:view compliance')->name('index');
        Route::post('/', [ComplianceController::class, 'store'])->name('store');
        Route::post('/import', [ComplianceController::class, 'import'])->name('import');
        Route::match(['put', 'patch'], '/{compliance}', [ComplianceController::class, 'update'])->name('update');
        Route::delete('/{compliance}', [ComplianceController::class, 'destroy'])->name('destroy');
    });

    // Proposal template library (Phase 7)
    Route::prefix('templates')->name('templates.')->group(function () {
        Route::get('/', [TemplateController::class, 'index'])->middleware('can:view templates')->name('index');
        Route::post('/', [TemplateController::class, 'store'])->name('store');
        Route::match(['put', 'patch'], '/{template}', [TemplateController::class, 'update'])->name('update');
        Route::delete('/{template}', [TemplateController::class, 'destroy'])->name('destroy');
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
        Route::post('/read', [FollowUpController::class, 'markRead'])->name('read');
        Route::post('/delete', [FollowUpController::class, 'destroyMany'])->name('delete-many');
        Route::post('/pin', [FollowUpController::class, 'togglePin'])->name('pin');
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
        // Proposal Writer workspace — must precede the /{aiAnalysis} wildcard.
        Route::get('/writer', [AiAssistantController::class, 'writer'])->name('writer');
        // Datasheet Writer — sits with the Proposal Writer; all of these must also
        // precede the /{aiAnalysis} wildcard below.
        Route::get('/datasheets', [\App\Http\Controllers\Web\DatasheetController::class, 'index'])->name('datasheets.index');
        Route::post('/datasheets', [\App\Http\Controllers\Web\DatasheetController::class, 'store'])->name('datasheets.store');
        Route::get('/datasheets/{datasheet}', [\App\Http\Controllers\Web\DatasheetController::class, 'show'])->name('datasheets.show');
        Route::match(['put', 'patch', 'post'], '/datasheets/{datasheet}/edit', [\App\Http\Controllers\Web\DatasheetController::class, 'update'])->name('datasheets.update');
        Route::post('/datasheets/{datasheet}/regenerate', [\App\Http\Controllers\Web\DatasheetController::class, 'regenerate'])->name('datasheets.regenerate');
        Route::get('/datasheets/{datasheet}/download', [\App\Http\Controllers\Web\DatasheetController::class, 'download'])->name('datasheets.download');
        Route::delete('/datasheets/{datasheet}', [\App\Http\Controllers\Web\DatasheetController::class, 'destroy'])->name('datasheets.destroy');
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
        // Org-wide proposal Style Profile (admin only) — drives the AI writer + export.
        Route::get('/proposal-style', [SettingsController::class, 'proposalStyle'])->name('proposal-style');
        Route::match(['put', 'patch', 'post'], '/proposal-style', [SettingsController::class, 'updateProposalStyle'])->name('proposal-style.update');
        // Per-user work email (SMTP) connection.
        Route::match(['put', 'patch', 'post'], '/mailbox', [SettingsController::class, 'connectMailbox'])->name('mailbox');
        Route::post('/mailbox/test', [SettingsController::class, 'testMailbox'])->name('mailbox.test');
        Route::delete('/mailbox', [SettingsController::class, 'disconnectMailbox'])->name('mailbox.disconnect');
    });
});
