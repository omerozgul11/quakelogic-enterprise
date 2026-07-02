<?php

use App\Modules\Procurement\Http\Controllers\ApprovalController;
use App\Modules\Procurement\Http\Controllers\ApprovalFlowController;
use App\Modules\Procurement\Http\Controllers\AttachmentController;
use App\Modules\Procurement\Http\Controllers\BillController;
use App\Modules\Procurement\Http\Controllers\DashboardController;
use App\Modules\Procurement\Http\Controllers\PurchaseOrderController;
use App\Modules\Procurement\Http\Controllers\PurchaseRequestController;
use App\Modules\Procurement\Http\Controllers\QuotationController;
use App\Modules\Procurement\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

/*
 | Procurement module — web routes. Loaded by ProcurementServiceProvider inside
 | the shared [web, auth, verified] group; "access procurement" gates the section.
 */
Route::prefix('procurement')->name('procurement.')->middleware('permission:access procurement')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Polymorphic document attachments (PR / Quotation / PO / Bill).
    Route::post('attachments/{entity}/{id}', [AttachmentController::class, 'store'])->name('attachments.store')
        ->whereIn('entity', ['purchase-requests', 'quotations', 'purchase-orders', 'bills'])->whereNumber('id');
    Route::get('attachments/{attachment}/download', [AttachmentController::class, 'download'])->name('attachments.download');
    Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

    // Multi-level approval chains — decisions on the current step + signatures.
    Route::post('approvals/{entity}/{id}/approve', [ApprovalController::class, 'approve'])->name('approvals.approve')
        ->whereIn('entity', ['purchase-requests', 'purchase-orders', 'bill-payments'])->whereNumber('id');
    Route::post('approvals/{entity}/{id}/reject', [ApprovalController::class, 'reject'])->name('approvals.reject')
        ->whereIn('entity', ['purchase-requests', 'purchase-orders', 'bill-payments'])->whereNumber('id');
    Route::get('approvals/signatures/{step}', [ApprovalController::class, 'signature'])->name('approvals.signature');

    // Approval-chain configuration (admin).
    Route::middleware('permission:manage approval flows')->group(function () {
        Route::get('approval-flows', [ApprovalFlowController::class, 'index'])->name('approval-flows.index');
        Route::post('approval-flows', [ApprovalFlowController::class, 'store'])->name('approval-flows.store');
        Route::match(['put', 'patch'], 'approval-flows/{approvalFlow}', [ApprovalFlowController::class, 'update'])->name('approval-flows.update');
        Route::delete('approval-flows/{approvalFlow}', [ApprovalFlowController::class, 'destroy'])->name('approval-flows.destroy');
    });

    Route::prefix('suppliers')->name('suppliers.')->group(function () {
        Route::get('/', [SupplierController::class, 'index'])->name('index');
        Route::post('/', [SupplierController::class, 'store'])->name('store');
        Route::get('/{supplier}', [SupplierController::class, 'show'])->name('show');
        Route::match(['put', 'patch'], '/{supplier}', [SupplierController::class, 'update'])->name('update');
        Route::delete('/{supplier}', [SupplierController::class, 'destroy'])->name('destroy');

        Route::post('/{supplier}/contacts', [SupplierController::class, 'storeContact'])->name('contacts.store');
        Route::match(['put', 'patch'], '/{supplier}/contacts/{contact}', [SupplierController::class, 'updateContact'])->name('contacts.update');
        Route::post('/{supplier}/contacts/{contact}/portal', [SupplierController::class, 'contactPortal'])->name('contacts.portal');
        Route::delete('/{supplier}/contacts/{contact}', [SupplierController::class, 'destroyContact'])->name('contacts.destroy');
    });

    Route::prefix('purchase-requests')->name('purchase-requests.')->group(function () {
        Route::get('/', [PurchaseRequestController::class, 'index'])->name('index');
        Route::get('/create', [PurchaseRequestController::class, 'create'])->name('create');
        Route::post('/', [PurchaseRequestController::class, 'store'])->name('store');
        Route::post('/from-invoice/{invoice}', [PurchaseRequestController::class, 'storeFromInvoice'])->name('from-invoice');
        Route::get('/{purchaseRequest}', [PurchaseRequestController::class, 'show'])->name('show');
        Route::match(['put', 'patch'], '/{purchaseRequest}', [PurchaseRequestController::class, 'update'])->name('update');
        Route::delete('/{purchaseRequest}', [PurchaseRequestController::class, 'destroy'])->name('destroy');

        Route::get('/{purchaseRequest}/pdf', [PurchaseRequestController::class, 'pdf'])->name('pdf');
        Route::post('/{purchaseRequest}/send-email', [PurchaseRequestController::class, 'sendEmail'])->name('send-email');
        Route::post('/{purchaseRequest}/submit', [PurchaseRequestController::class, 'submit'])->name('submit');
        Route::post('/{purchaseRequest}/approve', [PurchaseRequestController::class, 'approve'])->name('approve');
        Route::post('/{purchaseRequest}/reject', [PurchaseRequestController::class, 'reject'])->name('reject');
        Route::post('/{purchaseRequest}/cancel', [PurchaseRequestController::class, 'cancel'])->name('cancel');
        Route::post('/{purchaseRequest}/convert-to-quotation', [PurchaseRequestController::class, 'convertToQuotation'])->name('convert-quotation');
        Route::post('/{purchaseRequest}/convert-to-order', [PurchaseRequestController::class, 'convertToOrder'])->name('convert-order');
    });

    Route::prefix('quotations')->name('quotations.')->group(function () {
        Route::get('/', [QuotationController::class, 'index'])->name('index');
        Route::get('/create', [QuotationController::class, 'create'])->name('create');
        Route::post('/', [QuotationController::class, 'store'])->name('store');
        Route::get('/{quotation}', [QuotationController::class, 'show'])->name('show');
        Route::match(['put', 'patch'], '/{quotation}', [QuotationController::class, 'update'])->name('update');
        Route::delete('/{quotation}', [QuotationController::class, 'destroy'])->name('destroy');

        Route::get('/{quotation}/pdf', [QuotationController::class, 'pdf'])->name('pdf');
        Route::post('/{quotation}/send-email', [QuotationController::class, 'sendEmail'])->name('send-email');
        Route::post('/{quotation}/send', [QuotationController::class, 'send'])->name('send');
        Route::post('/{quotation}/received', [QuotationController::class, 'markReceived'])->name('received');
        Route::post('/{quotation}/reject', [QuotationController::class, 'reject'])->name('reject');
        Route::post('/{quotation}/accept', [QuotationController::class, 'accept'])->name('accept');
    });

    Route::prefix('bills')->name('bills.')->group(function () {
        Route::get('/', [BillController::class, 'index'])->name('index');
        Route::get('/create', [BillController::class, 'create'])->name('create');
        Route::post('/', [BillController::class, 'store'])->name('store');
        Route::post('/from-order/{purchaseOrder}', [BillController::class, 'storeFromOrder'])->name('from-order');
        Route::get('/{bill}', [BillController::class, 'show'])->name('show');
        Route::match(['put', 'patch'], '/{bill}', [BillController::class, 'update'])->name('update');
        Route::delete('/{bill}', [BillController::class, 'destroy'])->name('destroy');

        Route::get('/{bill}/pdf', [BillController::class, 'pdf'])->name('pdf');
        Route::post('/{bill}/payments', [BillController::class, 'recordPayment'])->name('payments.store');
        Route::post('/{bill}/payments/{payment}/approve', [BillController::class, 'approvePayment'])->name('payments.approve');
        Route::post('/{bill}/payments/{payment}/reject', [BillController::class, 'rejectPayment'])->name('payments.reject');
    });

    Route::prefix('purchase-orders')->name('purchase-orders.')->group(function () {
        Route::get('/', [PurchaseOrderController::class, 'index'])->name('index');
        Route::get('/create', [PurchaseOrderController::class, 'create'])->name('create');
        Route::post('/', [PurchaseOrderController::class, 'store'])->name('store');
        Route::post('/from-invoice/{invoice}', [PurchaseOrderController::class, 'storeFromInvoice'])->name('from-invoice');
        Route::get('/{purchaseOrder}/edit', [PurchaseOrderController::class, 'edit'])->name('edit');
        Route::get('/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('show');
        Route::match(['put', 'patch'], '/{purchaseOrder}', [PurchaseOrderController::class, 'update'])->name('update');
        Route::delete('/{purchaseOrder}', [PurchaseOrderController::class, 'destroy'])->name('destroy');

        Route::get('/{purchaseOrder}/pdf', [PurchaseOrderController::class, 'pdf'])->name('pdf');
        Route::post('/{purchaseOrder}/send-email', [PurchaseOrderController::class, 'sendEmail'])->name('send-email');
        Route::post('/{purchaseOrder}/submit', [PurchaseOrderController::class, 'submit'])->name('submit');
        Route::post('/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])->name('approve');
        Route::post('/{purchaseOrder}/sent', [PurchaseOrderController::class, 'markSent'])->name('sent');
        Route::post('/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->name('cancel');
        Route::post('/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])->name('receive');
    });
});
