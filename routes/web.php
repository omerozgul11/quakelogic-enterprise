<?php

use App\Http\Controllers\Web\AiAssistantController;
use App\Http\Controllers\Web\CalendarController;
use App\Http\Controllers\Web\CommissionController;
use App\Http\Controllers\Web\ComplianceController;
use App\Http\Controllers\Web\ContractController;
use App\Http\Controllers\Web\TemplateController;
use App\Http\Controllers\Web\CrmController;
use App\Http\Controllers\Web\Crm\ActivityController as CrmActivityController;
use App\Http\Controllers\Web\Crm\AutomationController as CrmAutomationController;
use App\Http\Controllers\Web\Crm\ClientController as CrmClientController;
use App\Http\Controllers\Web\Crm\ContactController as CrmContactController;
use App\Http\Controllers\Web\Crm\DashboardController as CrmDashboardController;
use App\Http\Controllers\Web\Crm\DuplicateController as CrmDuplicateController;
use App\Http\Controllers\Web\Crm\FollowUpController as CrmFollowUpController;
use App\Http\Controllers\Web\Crm\InvoiceController as CrmInvoiceController;
use App\Http\Controllers\Web\Crm\LeadController as CrmLeadController;
use App\Http\Controllers\Web\Crm\LeaveController as CrmLeaveController;
use App\Http\Controllers\Web\Crm\ProjectController as CrmProjectController;
use App\Http\Controllers\Web\Crm\QuickContactController as CrmQuickContactController;
use App\Http\Controllers\Web\Crm\ProjectSettingsController as CrmProjectSettingsController;
use App\Http\Controllers\Web\Crm\ReportController as CrmReportController;
use App\Http\Controllers\Web\Crm\TimeClockController as CrmTimeClockController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DocumentController;
use App\Http\Controllers\Web\FollowUpController;
use App\Http\Controllers\Web\OpportunityController;
use App\Http\Controllers\Web\OpportunityOversightController;
use App\Http\Controllers\Web\ProposalController;
use App\Http\Controllers\Web\ProposalCostController;
use App\Http\Controllers\Web\ReportController;
use App\Http\Controllers\Web\AdminController;
use App\Http\Controllers\Web\BidPrimeAdminController;
use App\Http\Controllers\Web\OpportunityKeywordGroupController;
use App\Http\Controllers\Web\SettingsController;
use App\Http\Controllers\Web\IntegrationController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\ImpersonationController;
use App\Http\Controllers\Web\SearchController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Auth routes handled by Fortify
require __DIR__.'/auth.php';

// Public legal / terms / copyright page (linked from the footer).
Route::get('/legal', fn () => Inertia::render('Legal/Index'))->name('legal');

// Stop impersonating — reachable while impersonating any user (the active
// session user is the impersonated one, not a Super Admin), so it sits outside
// the role gate and the `verified` middleware. Starting impersonation lives in
// the Super-Admin admin group below.
Route::post('/impersonate/stop', [ImpersonationController::class, 'stop'])
    ->middleware('auth')->name('impersonate.stop');

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
        // Dashboard "Sync now": pull newly-created labels from the carrier + refresh.
        Route::post('/sync', [\App\Http\Controllers\Web\MailingController::class, 'sync'])->name('sync');
        Route::get('/carriers', [\App\Http\Controllers\Web\CarriersController::class, 'index'])->name('carriers');
        Route::post('/carriers', [\App\Http\Controllers\Web\CarriersController::class, 'store'])->name('carriers.store');
        Route::post('/carriers/update', [\App\Http\Controllers\Web\CarriersController::class, 'update'])->name('carriers.update');
        Route::post('/carriers/profile', [\App\Http\Controllers\Web\CarriersController::class, 'updateProfile'])->name('carriers.profile');
        Route::post('/carriers/remove', [\App\Http\Controllers\Web\CarriersController::class, 'destroy'])->name('carriers.remove');
        Route::post('/carriers/restore', [\App\Http\Controllers\Web\CarriersController::class, 'restore'])->name('carriers.restore');
        // DHL tracking push subscriptions — connect a tracking number / account so
        // DHL pushes live updates to our webhook, or disconnect one.
        Route::post('/carriers/dhl/subscribe', [\App\Http\Controllers\Web\DhlSubscriptionController::class, 'subscribe'])->name('carriers.dhl.subscribe');
        Route::post('/carriers/dhl/unsubscribe', [\App\Http\Controllers\Web\DhlSubscriptionController::class, 'destroy'])->name('carriers.dhl.unsubscribe');

        // Rate / spot-price quotes (manual today; live for credentialed carriers like DHL).
        Route::prefix('rates')->name('rates.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Web\RateQuoteController::class, 'index'])->name('index');
            Route::get('/create', [\App\Http\Controllers\Web\RateQuoteController::class, 'create'])->name('create');
            // Instant estimate from the DHL contract rate card (JSON; no spot-quote email needed).
            Route::post('/estimate', [\App\Http\Controllers\Web\RateQuoteController::class, 'estimate'])->name('estimate');
            Route::post('/', [\App\Http\Controllers\Web\RateQuoteController::class, 'store'])->name('store');
            Route::get('/{ulid}/edit', [\App\Http\Controllers\Web\RateQuoteController::class, 'edit'])->name('edit');
            Route::match(['put', 'patch'], '/{ulid}', [\App\Http\Controllers\Web\RateQuoteController::class, 'update'])->name('update');
            Route::post('/{ulid}/request', [\App\Http\Controllers\Web\RateQuoteController::class, 'markRequested'])->name('request');
            // Returned rate-sheet PDF (attach → AI extract → download / remove).
            Route::post('/{ulid}/document', [\App\Http\Controllers\Web\RateQuoteController::class, 'uploadDocument'])->name('document.store');
            Route::get('/{ulid}/document/download', [\App\Http\Controllers\Web\RateQuoteController::class, 'downloadDocument'])->name('document.download');
            Route::delete('/{ulid}/document', [\App\Http\Controllers\Web\RateQuoteController::class, 'deleteDocument'])->name('document.destroy');
            Route::post('/{ulid}/extract', [\App\Http\Controllers\Web\RateQuoteController::class, 'extract'])->name('extract');
            Route::delete('/{ulid}', [\App\Http\Controllers\Web\RateQuoteController::class, 'destroy'])->name('destroy');
        });

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
            Route::post('/bulk-refresh', [\App\Http\Controllers\Web\MailingController::class, 'bulkRefresh'])->name('bulk-refresh');
            Route::post('/bulk-reassign', [\App\Http\Controllers\Web\MailingController::class, 'bulkReassign'])->name('bulk-reassign');
            Route::post('/bulk-delete', [\App\Http\Controllers\Web\MailingController::class, 'bulkDelete'])->name('bulk-delete');
            // Before /{ulid} so "export" isn't read as a shipment id.
            Route::get('/export', [\App\Http\Controllers\Web\MailingController::class, 'export'])->name('export');
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

        // Quick Contacts — shared rolodex of frequently-dialed reference numbers.
        Route::prefix('quick-contacts')->name('quick-contacts.')->group(function () {
            Route::get('/', [CrmQuickContactController::class, 'index'])->name('index');
            Route::post('/', [CrmQuickContactController::class, 'store'])->name('store');
            Route::match(['put', 'patch'], '/{quickContact}', [CrmQuickContactController::class, 'update'])->name('update');
            Route::delete('/{quickContact}', [CrmQuickContactController::class, 'destroy'])->name('destroy');
        });

        // Leads & sales pipeline
        Route::prefix('leads')->name('leads.')->group(function () {
            Route::get('/', [CrmLeadController::class, 'index'])->name('index');
            Route::post('/', [CrmLeadController::class, 'store'])->name('store');
            Route::get('/{lead}', [CrmLeadController::class, 'show'])->name('show');
            Route::match(['put', 'patch'], '/{lead}', [CrmLeadController::class, 'update'])->name('update');
            Route::post('/{lead}/status', [CrmLeadController::class, 'updateStatus'])->name('status');
            Route::post('/{lead}/convert', [CrmLeadController::class, 'convert'])->name('convert');
            Route::delete('/{lead}', [CrmLeadController::class, 'destroy'])->name('destroy');
        });

        // Activity timeline entries (note / call / email / meeting) on CRM records.
        Route::prefix('activities')->name('activities.')->group(function () {
            Route::post('/', [CrmActivityController::class, 'store'])->name('store');
            Route::delete('/{activity}', [CrmActivityController::class, 'destroy'])->name('destroy');
        });

        // Sales reporting & forecasting (read-only analytics).
        Route::get('/reports', [CrmReportController::class, 'index'])->name('reports.index');

        // Duplicate detection & merge (companies + contacts).
        Route::get('/duplicates', [CrmDuplicateController::class, 'index'])->name('duplicates.index');
        Route::post('/duplicates/merge', [CrmDuplicateController::class, 'merge'])->name('duplicates.merge');

        // Rules-based automations.
        Route::prefix('automations')->name('automations.')->group(function () {
            Route::get('/', [CrmAutomationController::class, 'index'])->name('index');
            Route::post('/', [CrmAutomationController::class, 'store'])->name('store');
            Route::match(['put', 'patch'], '/{automation}', [CrmAutomationController::class, 'update'])->name('update');
            Route::post('/{automation}/toggle', [CrmAutomationController::class, 'toggle'])->name('toggle');
            Route::delete('/{automation}', [CrmAutomationController::class, 'destroy'])->name('destroy');
        });

        // Follow-up tasks + Today/Overdue/Upcoming queue.
        Route::prefix('follow-ups')->name('follow-ups.')->group(function () {
            Route::get('/', [CrmFollowUpController::class, 'index'])->name('index');
            Route::post('/', [CrmFollowUpController::class, 'store'])->name('store');
            Route::match(['put', 'patch'], '/{followUp}', [CrmFollowUpController::class, 'update'])->name('update');
            Route::post('/{followUp}/complete', [CrmFollowUpController::class, 'complete'])->name('complete');
            Route::delete('/{followUp}', [CrmFollowUpController::class, 'destroy'])->name('destroy');
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
            Route::post('/{invoice}/create-project', [CrmInvoiceController::class, 'createProject'])->name('create-project');
            Route::post('/{invoice}/payments', [CrmInvoiceController::class, 'storePayment'])->name('payments.store');
            Route::delete('/{invoice}/payments/{payment}', [CrmInvoiceController::class, 'destroyPayment'])->name('payments.destroy');
        });

        // Time clock (live punch widget, JSON) + Time Cards (review/filter shifts).
        Route::prefix('time-clock')->name('time-clock.')->group(function () {
            Route::get('/status', [CrmTimeClockController::class, 'status'])->name('status');
            Route::post('/in', [CrmTimeClockController::class, 'clockIn'])->name('in');
            Route::post('/out', [CrmTimeClockController::class, 'clockOut'])->name('out');
        });
        Route::prefix('time-cards')->name('time-cards.')->group(function () {
            Route::get('/', [CrmTimeClockController::class, 'index'])->name('index');
            Route::post('/', [CrmTimeClockController::class, 'store'])->name('store');
            Route::match(['put', 'patch'], '/{timeEntry}', [CrmTimeClockController::class, 'update'])->name('update');
            Route::delete('/{timeEntry}', [CrmTimeClockController::class, 'destroy'])->name('destroy');
        });

        // Team leave (powers the "On leave" count on the dashboard presence strip).
        Route::prefix('leave')->name('leave.')->group(function () {
            Route::post('/', [CrmLeaveController::class, 'store'])->name('store');
            Route::delete('/{leave}', [CrmLeaveController::class, 'destroy'])->name('destroy');
        });
    });

    // Projects — the Project Management app at /projects (its own app-switcher
    // tile + layout). Reuses the CRM project controllers; gated by `access crm`
    // (every role has it). Projects are created automatically from won proposals.
    Route::prefix('projects')->name('projects.')->middleware('permission:access crm')->group(function () {
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
        Route::post('/{project}/expenses', [CrmProjectController::class, 'storeExpense'])->name('expenses.store');

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

        // Vendor contacts (forklift, trucking, crane, …)
        Route::post('/{project}/vendors', [CrmProjectController::class, 'storeVendor'])->name('vendors.store');
        Route::match(['put', 'patch'], '/{project}/vendors/{vendor}', [CrmProjectController::class, 'updateVendor'])->name('vendors.update');
        Route::delete('/{project}/vendors/{vendor}', [CrmProjectController::class, 'destroyVendor'])->name('vendors.destroy');

        // Installation sites (site & safety briefing)
        Route::post('/{project}/sites', [CrmProjectController::class, 'storeSite'])->name('sites.store');
        Route::match(['put', 'patch'], '/{project}/sites/{site}', [CrmProjectController::class, 'updateSite'])->name('sites.update');
        Route::delete('/{project}/sites/{site}', [CrmProjectController::class, 'destroySite'])->name('sites.destroy');

        // Typed site / stakeholder contacts
        Route::post('/{project}/contacts', [CrmProjectController::class, 'storeContact'])->name('contacts.store');
        Route::match(['put', 'patch'], '/{project}/contacts/{contact}', [CrmProjectController::class, 'updateContact'])->name('contacts.update');
        Route::delete('/{project}/contacts/{contact}', [CrmProjectController::class, 'destroyContact'])->name('contacts.destroy');

        // Equipment being installed
        Route::post('/{project}/equipment', [CrmProjectController::class, 'storeEquipment'])->name('equipment.store');
        Route::match(['put', 'patch'], '/{project}/equipment/{equipment}', [CrmProjectController::class, 'updateEquipment'])->name('equipment.update');
        Route::delete('/{project}/equipment/{equipment}', [CrmProjectController::class, 'destroyEquipment'])->name('equipment.destroy');

        // Shipments (equipment moving to / from site)
        Route::post('/{project}/shipments', [CrmProjectController::class, 'storeShipment'])->name('shipments.store');
        Route::match(['put', 'patch'], '/{project}/shipments/{shipment}', [CrmProjectController::class, 'updateShipment'])->name('shipments.update');
        Route::delete('/{project}/shipments/{shipment}', [CrmProjectController::class, 'destroyShipment'])->name('shipments.destroy');

        // Execution records (installation / commissioning / training / …)
        Route::post('/{project}/execution-records', [CrmProjectController::class, 'storeExecutionRecord'])->name('execution-records.store');
        Route::match(['put', 'patch'], '/{project}/execution-records/{record}', [CrmProjectController::class, 'updateExecutionRecord'])->name('execution-records.update');
        Route::delete('/{project}/execution-records/{record}', [CrmProjectController::class, 'destroyExecutionRecord'])->name('execution-records.destroy');

        // Checklists & items
        Route::post('/{project}/checklists', [CrmProjectController::class, 'storeChecklist'])->name('checklists.store');
        Route::match(['put', 'patch'], '/{project}/checklists/{checklist}', [CrmProjectController::class, 'updateChecklist'])->name('checklists.update');
        Route::delete('/{project}/checklists/{checklist}', [CrmProjectController::class, 'destroyChecklist'])->name('checklists.destroy');
        Route::post('/{project}/checklists/{checklist}/items', [CrmProjectController::class, 'storeChecklistItem'])->name('checklists.items.store');
        Route::match(['put', 'patch'], '/{project}/checklists/{checklist}/items/{item}', [CrmProjectController::class, 'updateChecklistItem'])->name('checklists.items.update');
        Route::delete('/{project}/checklists/{checklist}/items/{item}', [CrmProjectController::class, 'destroyChecklistItem'])->name('checklists.items.destroy');

        // Travel arrangements
        Route::post('/{project}/travel', [CrmProjectController::class, 'storeTravel'])->name('travel.store');
        Route::match(['put', 'patch'], '/{project}/travel/{travel}', [CrmProjectController::class, 'updateTravel'])->name('travel.update');
        Route::delete('/{project}/travel/{travel}', [CrmProjectController::class, 'destroyTravel'])->name('travel.destroy');

        // Digital sign-offs
        Route::post('/{project}/signoffs', [CrmProjectController::class, 'storeSignoff'])->name('signoffs.store');
        Route::delete('/{project}/signoffs/{signoff}', [CrmProjectController::class, 'destroySignoff'])->name('signoffs.destroy');

        // AI field briefing + printable Field Packet (PDF)
        Route::post('/{project}/briefing', [CrmProjectController::class, 'generateBriefing'])->name('briefing.generate');
        Route::get('/{project}/field-packet', [CrmProjectController::class, 'fieldPacket'])->name('field-packet');

        // Purchase orders (links to the Procurement module's PO records)
        Route::post('/{project}/purchase-orders', [CrmProjectController::class, 'attachPurchaseOrder'])->name('purchase-orders.attach');
        Route::delete('/{project}/purchase-orders/{purchaseOrder}', [CrmProjectController::class, 'detachPurchaseOrder'])->name('purchase-orders.detach');

        // Files (with folders + version history)
        Route::post('/{project}/files', [CrmProjectController::class, 'storeFile'])->name('files.store');
        Route::get('/{project}/files/{file}/download', [CrmProjectController::class, 'downloadFile'])->name('files.download');
        Route::patch('/{project}/files/{file}/move', [CrmProjectController::class, 'moveFile'])->name('files.move');
        Route::patch('/{project}/files/{file}/restore-version', [CrmProjectController::class, 'restoreFileVersion'])->name('files.restore-version');
        Route::delete('/{project}/files/{file}', [CrmProjectController::class, 'destroyFile'])->name('files.destroy');

        // Document folders
        Route::post('/{project}/folders', [CrmProjectController::class, 'storeFolder'])->name('folders.store');
        Route::match(['put', 'patch'], '/{project}/folders/{folder}', [CrmProjectController::class, 'updateFolder'])->name('folders.update');
        Route::delete('/{project}/folders/{folder}', [CrmProjectController::class, 'destroyFolder'])->name('folders.destroy');
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
        Route::get('/activity', [AdminController::class, 'activity'])->name('activity');
        Route::get('/users', [AdminController::class, 'users'])->name('users');
        Route::post('/users', [AdminController::class, 'storeUser'])->name('users.store');
        Route::match(['put', 'patch'], '/users/{user}', [AdminController::class, 'updateUser'])->name('users.update');
        Route::delete('/users/{user}', [AdminController::class, 'deleteUser'])->name('users.delete');
        // Start impersonating a teammate ("Login as"). Stop lives outside this
        // group (see /impersonate/stop) so the impersonated user can return.
        Route::post('/users/{user}/impersonate', [ImpersonationController::class, 'start'])->name('users.impersonate');
        // Admin-managed work email (SMTP) for a teammate.
        Route::match(['put', 'patch', 'post'], '/users/{user}/mailbox', [AdminController::class, 'connectUserMailbox'])->name('users.mailbox');
        Route::post('/users/{user}/mailbox/test', [AdminController::class, 'testUserMailbox'])->name('users.mailbox.test');
        Route::delete('/users/{user}/mailbox', [AdminController::class, 'disconnectUserMailbox'])->name('users.mailbox.disconnect');
        Route::get('/audit-logs', [AdminController::class, 'auditLogs'])->name('audit-logs');

        // Opportunity keyword groups — editable source of truth for scoring.
        Route::get('/keyword-groups', [OpportunityKeywordGroupController::class, 'index'])->name('keyword-groups.index');
        Route::post('/keyword-groups', [OpportunityKeywordGroupController::class, 'store'])->name('keyword-groups.store');
        Route::match(['put', 'patch'], '/keyword-groups/{keywordGroup}', [OpportunityKeywordGroupController::class, 'update'])->name('keyword-groups.update');
        Route::delete('/keyword-groups/{keywordGroup}', [OpportunityKeywordGroupController::class, 'destroy'])->name('keyword-groups.destroy');

        // BidPrime email intake — review dashboard, reprocess + approve/reject.
        Route::prefix('bidprime')->name('bidprime.')->group(function () {
            Route::get('/', [BidPrimeAdminController::class, 'index'])->name('index');
            Route::get('/emails/{email}', [BidPrimeAdminController::class, 'showEmail'])->name('emails.show');
            Route::post('/import-now', [BidPrimeAdminController::class, 'importNow'])->name('import-now');
            Route::post('/reprocess-recent', [BidPrimeAdminController::class, 'reprocessRecent'])->name('reprocess-recent');
            Route::post('/reprocess-failed', [BidPrimeAdminController::class, 'reprocessFailed'])->name('reprocess-failed');
            Route::post('/emails/{email}/reprocess', [BidPrimeAdminController::class, 'reprocessEmail'])->name('emails.reprocess');
            Route::post('/opportunities/{opportunity}/approve', [BidPrimeAdminController::class, 'approveOpportunity'])->name('opportunities.approve');
            Route::post('/opportunities/{opportunity}/reject', [BidPrimeAdminController::class, 'rejectOpportunity'])->name('opportunities.reject');
        });
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
