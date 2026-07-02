<?php

namespace App\Modules\Procurement\Console;

use App\Models\AuditLog;
use App\Models\User;
use App\Modules\Inventory\Models\Product;
use App\Modules\Procurement\Enums\BillPaymentApprovalStatus;
use App\Modules\Procurement\Enums\BillPaymentStatus;
use App\Modules\Procurement\Enums\PurchaseOrderStatus;
use App\Modules\Procurement\Enums\PurchaseRequestStatus;
use App\Modules\Procurement\Enums\QuotationStatus;
use App\Modules\Procurement\Models\Bill;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\PurchaseRequest;
use App\Modules\Procurement\Models\Quotation;
use App\Modules\Procurement\Models\Supplier;
use App\Modules\Procurement\Models\SupplierContact;
use App\Modules\Procurement\Services\BillService;
use App\Modules\Procurement\Services\ProcurementNumberService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Migrates the legacy RISE "Purchase" plugin data (rise_pur_*) into our
 * Procurement module: vendors → suppliers, purchase requests, quotations,
 * purchase orders, and bills (vendor invoices) with their payments.
 *
 * Reuses the same isolated `portal_import` source database and the shared
 * `migration_map` table that portal:migrate uses, so it is idempotent, safe to
 * re-run, and can resolve staff ids through the CRM migration's `user` map. It
 * NEVER deletes or truncates anything. Use --dry-run to preview counts.
 */
class PortalMigratePurchasingCommand extends Command
{
    protected $signature = 'portal:migrate-purchasing
        {--only= : Comma list: vendors,requests,quotations,orders,bills. Default: all in order}
        {--org=1 : Target organization id}
        {--owner= : Fallback owner/creator user id (defaults to the lowest-id user in the org)}
        {--source=portal_import : Source database (already loaded from the RISE dump)}
        {--dry-run : Report what would happen; write nothing}';

    protected $description = 'Migrate purchasing data (vendors, requests, quotations, POs, bills) from the legacy RISE portal';

    private bool $dry = false;
    private int $org = 1;
    private int $fallbackOwnerId;
    private array $mem = [];
    private int $dryCounter = 0;
    private array $productBySku = [];

    public function handle(ProcurementNumberService $numbers, BillService $bills): int
    {
        $this->dry = (bool) $this->option('dry-run');
        $this->org = (int) $this->option('org');

        config(['database.connections.portal' => array_merge(
            config('database.connections.mysql'),
            ['database' => $this->option('source')],
        )]);
        DB::purge('portal');

        try {
            DB::connection('portal')->getPdo();
        } catch (\Throwable $e) {
            $this->error("Cannot reach source database '{$this->option('source')}': {$e->getMessage()}");

            return self::FAILURE;
        }

        $owner = $this->option('owner') ? (int) $this->option('owner')
            : (int) User::where('organization_id', $this->org)->orderBy('id')->value('id');
        if (! $owner) {
            $this->error("No fallback owner: organization {$this->org} has no users. Pass --owner=<id>.");

            return self::FAILURE;
        }
        $this->fallbackOwnerId = $owner;
        $this->ensureMapTable();

        $this->line($this->dry ? '<comment>DRY RUN — no data will be written.</comment>' : '<info>LIVE RUN — writing data.</info>');
        $this->line("Target org: {$this->org} · fallback owner user: {$this->fallbackOwnerId}");
        $this->newLine();

        $only = array_filter(array_map('trim', explode(',', (string) $this->option('only'))));
        $run = fn (string $e) => empty($only) || in_array($e, $only, true);

        AuditLog::withoutAuditing(function () use ($run, $numbers, $bills) {
            if ($run('vendors')) {
                $this->migrateVendors();
            }
            if ($run('requests')) {
                $this->migrateRequests($numbers);
            }
            if ($run('quotations')) {
                $this->migrateQuotations($numbers);
            }
            if ($run('orders')) {
                $this->migrateOrders($numbers);
            }
            if ($run('bills')) {
                $this->migrateBills($numbers, $bills);
            }
        });

        $this->newLine();
        $this->info($this->dry ? 'Dry run complete.' : 'Purchasing migration complete.');

        return self::SUCCESS;
    }

    private function migrateVendors(): void
    {
        $rows = DB::connection('portal')->table('rise_pur_vendor')->get();
        [$created, $matched, $updated, $contactsAdded] = [0, 0, 0, 0];

        // Vendor contact people (with emails + portal logins) live in rise_users
        // as user_type='vendor' rows linked by vendor_id. Import them and backfill
        // the supplier's own contact fields.
        $canPullContacts = $this->sourceHasColumn('rise_users', 'vendor_id');

        foreach ($rows as $v) {
            $vid = (int) $v->userid;
            $fields = $this->vendorContactFields($v);
            $supplierId = $this->getMap('pur_vendor', $vid);

            if ($supplierId) {
                $matched++;
                // Re-runs backfill only-empty contact fields on the existing supplier.
                if (! $this->dry && ($supplier = Supplier::find($supplierId)) && $this->backfill($supplier, $fields)) {
                    $updated++;
                }
            } elseif ($this->dry) {
                $this->putMap('pur_vendor', $vid, $this->dryId(), 'create');
                $created++;
            } else {
                $supplier = Supplier::create(array_merge([
                    'organization_id' => $this->org,
                    'created_by' => $this->ownerFor($v->addedfrom ?? null),
                    'owner_id' => $this->fallbackOwnerId,
                    'code' => $this->uniqueCode($this->clean($v->vendor_code, 40) ?: ('V'.$vid)),
                    'name' => $this->clean($v->company, 255) ?? ('Vendor '.$vid),
                    'status' => ((int) ($v->active ?? 1) === 1) ? 'active' : 'inactive',
                    'currency' => $this->cur($v->default_currency ?? null),
                    'metadata' => ['portal_vendor' => $vid],
                ], $fields));
                $this->putMap('pur_vendor', $vid, (int) $supplier->id, 'create');
                $supplierId = (int) $supplier->id;
                $created++;
            }

            if ($canPullContacts && $supplierId && ! $this->dry) {
                $contactsAdded += $this->importVendorContacts($vid, $supplierId);
            }
        }

        $this->reportEntity('Vendors', $rows->count(), $created, $matched);
        if ($updated || $contactsAdded) {
            $this->line(sprintf('  %-16s backfilled=%-5d contacts+=%-5d', '', $updated, $contactsAdded));
        }
    }

    /** Supplier contact fields from a RISE vendor row, with billing/shipping address fallback. */
    private function vendorContactFields(object $v): array
    {
        return [
            'category' => $this->clean($v->category ?? null, 120),
            'phone' => $this->clean($v->phonenumber ?? null, 40),
            'website' => $this->clean($v->website ?? null, 255),
            'address_line1' => $this->clean($v->address ?? null, 255) ?? $this->clean($v->billing_street ?? null, 255) ?? $this->clean($v->shipping_street ?? null, 255),
            'city' => $this->clean($v->city ?? null, 120) ?? $this->clean($v->billing_city ?? null, 120) ?? $this->clean($v->shipping_city ?? null, 120),
            'state' => $this->clean($v->state ?? null, 120) ?? $this->clean($v->billing_state ?? null, 120),
            'postal_code' => $this->clean($v->zip ?? null, 30) ?? $this->clean($v->billing_zip ?? null, 30),
            'country' => $this->clean($v->country ?? null, 120) ?? $this->clean($v->billing_country ?? null, 120),
            'payment_terms' => $this->clean($v->payment_terms ?? null, 60),
            'tax_id' => $this->clean($v->vat ?? null, 60),
        ];
    }

    /** Fill only the empty contact fields on an existing supplier; never overwrite. Returns true if changed. */
    private function backfill(Supplier $supplier, array $fields): bool
    {
        $changed = false;
        foreach ($fields as $key => $value) {
            if (($value ?? '') !== '' && ($supplier->{$key} ?? '') === '') {
                $supplier->{$key} = $value;
                $changed = true;
            }
        }
        if ($changed) {
            $supplier->save();
        }

        return $changed;
    }

    /**
     * Import a vendor's contact people (rise_users, user_type='vendor', linked by
     * vendor_id) into supplier contacts, and give the supplier a top-level email
     * (from its primary contact) if it has none. Idempotent by contact email.
     */
    private function importVendorContacts(int $vendorUserId, int $supplierId): int
    {
        $rows = DB::connection('portal')->table('rise_users')
            ->where('user_type', 'vendor')->where('vendor_id', $vendorUserId)->where('deleted', 0)
            ->get();
        if ($rows->isEmpty()) {
            return 0;
        }

        $supplier = Supplier::find($supplierId);
        $added = 0;
        $primaryEmail = null;

        foreach ($rows as $c) {
            $email = $this->clean($c->email ?? null, 255);
            $name = trim(($this->clean($c->first_name ?? null, 120) ?? '').' '.($this->clean($c->last_name ?? null, 120) ?? ''));
            if ($name === '' && ! $email) {
                continue;
            }
            $isPrimary = (int) ($c->is_primary_contact ?? 0) === 1;
            if ($isPrimary && $email) {
                $primaryEmail = $email;
            }

            $exists = SupplierContact::where('procurement_supplier_id', $supplierId)
                ->when($email !== null, fn ($q) => $q->where('email', $email))
                ->when($email === null, fn ($q) => $q->where('name', $name))
                ->exists();
            if ($exists) {
                continue;
            }

            SupplierContact::create([
                'organization_id' => $this->org,
                'procurement_supplier_id' => $supplierId,
                'name' => $name !== '' ? $name : ($email ?: 'Contact'),
                'title' => $this->clean($c->job_title ?? null, 120),
                'email' => $email,
                'phone' => $this->clean($c->phone ?? null, 40),
                'is_primary' => $isPrimary,
            ]);
            $added++;
        }

        if ($supplier && ($supplier->email ?? '') === '') {
            $fallback = $primaryEmail ?? $this->clean($rows->first()->email ?? null, 255);
            if ($fallback) {
                $supplier->forceFill(['email' => $fallback])->save();
            }
        }

        return $added;
    }

    private function sourceHasColumn(string $table, string $column): bool
    {
        return DB::connection('portal')->getSchemaBuilder()->hasColumn($table, $column);
    }

    private function migrateRequests(ProcurementNumberService $numbers): void
    {
        $rows = DB::connection('portal')->table('rise_pur_request')->get();
        [$created, $matched] = [0, 0];

        foreach ($rows as $r) {
            if ($this->getMap('pur_request', (int) $r->id)) {
                $matched++;
                continue;
            }
            if ($this->dry) {
                $this->putMap('pur_request', (int) $r->id, $this->dryId(), 'create');
                $created++;
                continue;
            }

            $pr = PurchaseRequest::create([
                'organization_id' => $this->org,
                'created_by' => $this->ownerFor($r->requester ?? null),
                'requester_id' => $this->ownerFor($r->requester ?? null),
                'number' => $numbers->purchaseRequest($this->org),
                'title' => $this->clean($r->pur_rq_name, 255) ?: ('Request '.$r->id),
                'description' => $this->clean($r->rq_description, 65000),
                'status' => $this->prStatus((int) $r->status),
                'currency' => $this->cur($r->currency ?? null),
                'subtotal' => (float) ($r->subtotal ?? 0),
                'tax_amount' => (float) ($r->total_tax ?? 0),
                'total' => (float) ($r->total ?? 0),
                'approved_at' => ((int) $r->status === 2) ? now() : null,
                'metadata' => ['portal_pur_request' => (int) $r->id, 'portal_code' => $r->pur_rq_code, 'portal_date' => (string) $r->request_date],
            ]);

            foreach (DB::connection('portal')->table('rise_pur_request_detail')->where('pur_request', $r->id)->orderBy('prd_id')->get() as $pos => $d) {
                $pr->items()->create($this->lineItem($d, $d->item_text ?? null, $pos));
            }

            $this->putMap('pur_request', (int) $r->id, (int) $pr->id, 'create');
            $created++;
        }

        $this->reportEntity('Requests', $rows->count(), $created, $matched);
    }

    private function migrateQuotations(ProcurementNumberService $numbers): void
    {
        $rows = DB::connection('portal')->table('rise_pur_estimates')->get();
        [$created, $matched] = [0, 0];

        foreach ($rows as $e) {
            if ($this->getMap('pur_estimate', (int) $e->id)) {
                $matched++;
                continue;
            }
            $vendorId = $this->getMap('pur_vendor', (int) $e->vendor);
            if (! $vendorId) {
                continue; // vendor not imported (out-of-scope vendor) — skip
            }
            if ($this->dry) {
                $this->putMap('pur_estimate', (int) $e->id, $this->dryId(), 'create');
                $created++;
                continue;
            }

            $q = Quotation::create([
                'organization_id' => $this->org,
                'created_by' => $this->ownerFor($e->addedfrom ?? null),
                'procurement_purchase_request_id' => $this->getMap('pur_request', (int) ($e->pur_request ?? 0)),
                'procurement_supplier_id' => $vendorId,
                'number' => $numbers->quotation($this->org),
                'reference_no' => $this->clean($e->reference_no, 120) ?: (string) ($e->number ?? ''),
                'status' => $this->quoteStatus((int) ($e->status ?? 1)),
                'quote_date' => $this->date($e->date ?? null),
                'expiry_date' => $this->date($e->expirydate ?? null),
                'currency' => $this->cur($e->currency ?? null),
                'subtotal' => (float) ($e->subtotal ?? 0),
                'tax_amount' => (float) ($e->total_tax ?? 0),
                'discount_total' => (float) ($e->discount_total ?? 0),
                'total' => (float) ($e->total ?? 0),
                'terms' => $this->clean($e->terms, 65000),
            ]);

            foreach (DB::connection('portal')->table('rise_pur_estimate_detail')->where('pur_estimate', $e->id)->orderBy('id')->get() as $pos => $d) {
                $q->items()->create($this->lineItem($d, $d->item_name ?? null, $pos));
            }

            $this->putMap('pur_estimate', (int) $e->id, (int) $q->id, 'create');
            $created++;
        }

        $this->reportEntity('Quotations', $rows->count(), $created, $matched);
    }

    private function migrateOrders(ProcurementNumberService $numbers): void
    {
        $rows = DB::connection('portal')->table('rise_pur_orders')->get();
        [$created, $matched, $skipped] = [0, 0, 0];

        foreach ($rows as $o) {
            if ($this->getMap('pur_order', (int) $o->id)) {
                $matched++;
                continue;
            }
            $vendorId = $this->getMap('pur_vendor', (int) $o->vendor);
            if (! $vendorId) {
                $skipped++;
                continue;
            }
            if ($this->dry) {
                $this->putMap('pur_order', (int) $o->id, $this->dryId(), 'create');
                $created++;
                continue;
            }

            $po = PurchaseOrder::create([
                'organization_id' => $this->org,
                'created_by' => $this->ownerFor($o->addedfrom ?? null),
                'procurement_supplier_id' => $vendorId,
                'procurement_purchase_request_id' => $this->getMap('pur_request', (int) ($o->pur_request ?? 0)),
                'procurement_quotation_id' => $this->getMap('pur_estimate', (int) ($o->estimate ?? 0)),
                'number' => $numbers->generate($this->org),
                'status' => $this->poStatus((int) ($o->approve_status ?? 1), (int) ($o->delivery_status ?? 0)),
                'order_date' => $this->date($o->order_date ?? null),
                'expected_date' => $this->date($o->delivery_date ?? null),
                'currency' => $this->cur($o->currency ?? null),
                'subtotal' => (float) ($o->subtotal ?? 0),
                'tax_rate' => 0,
                'tax_amount' => (float) ($o->total_tax ?? 0),
                'shipping_amount' => (float) ($o->shipping_fee ?? 0),
                'total' => (float) ($o->total ?? 0),
                'notes' => 'Legacy PO '.($o->pur_order_number ?? $o->id),
                'approved_at' => ((int) ($o->approve_status ?? 1) === 2) ? now() : null,
            ]);

            foreach (DB::connection('portal')->table('rise_pur_order_detail')->where('pur_order', $o->id)->orderBy('id')->get() as $pos => $d) {
                $po->items()->create([
                    'organization_id' => $this->org,
                    'inventory_product_id' => $this->productId($d->item_code ?? null),
                    'description' => $this->clean($d->item_name ?? $d->description ?? null, 255) ?: ($this->clean($d->item_code, 255) ?: 'Item'),
                    'sku' => $this->clean($d->item_code, 80),
                    'quantity_ordered' => (float) ($d->quantity ?? 0),
                    'quantity_received' => (float) ($d->wh_quantity_received ?? 0),
                    'unit_cost' => (float) ($d->unit_price ?? 0),
                    'line_total' => (float) ($d->into_money ?? ((float) ($d->quantity ?? 0) * (float) ($d->unit_price ?? 0))),
                    'position' => $pos,
                ]);
            }

            $this->putMap('pur_order', (int) $o->id, (int) $po->id, 'create');
            $created++;
        }

        $this->reportEntity('Purchase orders', $rows->count(), $created, $matched, $skipped);
    }

    private function migrateBills(ProcurementNumberService $numbers, BillService $billService): void
    {
        $rows = DB::connection('portal')->table('rise_pur_invoices')->get();
        [$created, $matched, $skipped] = [0, 0, 0];

        foreach ($rows as $inv) {
            if ($this->getMap('pur_invoice', (int) $inv->id)) {
                $matched++;
                continue;
            }
            $vendorId = $this->getMap('pur_vendor', (int) ($inv->vendor ?? 0));
            if (! $vendorId) {
                $skipped++;
                continue;
            }
            if ($this->dry) {
                $this->putMap('pur_invoice', (int) $inv->id, $this->dryId(), 'create');
                $created++;
                continue;
            }

            $bill = Bill::create([
                'organization_id' => $this->org,
                'created_by' => $this->ownerFor($inv->add_from ?? null),
                'procurement_supplier_id' => $vendorId,
                'procurement_purchase_order_id' => $this->getMap('pur_order', (int) ($inv->pur_order ?? 0)),
                'number' => $numbers->bill($this->org),
                'vendor_invoice_number' => $this->clean($inv->invoice_number, 120) ?: ('Legacy #'.$inv->number),
                'bill_date' => $this->date($inv->invoice_date ?? null),
                'due_date' => $this->date($inv->duedate ?? null),
                'currency' => $this->cur($inv->currency ?? null),
                'subtotal' => (float) ($inv->subtotal ?? 0),
                'tax_amount' => (float) ($inv->tax ?? 0),
                'shipping_amount' => (float) ($inv->shipping_fee ?? 0),
                'discount_total' => (float) ($inv->discount_total ?? 0),
                'total' => (float) ($inv->total ?? 0),
                'payment_status' => BillPaymentStatus::Unpaid,
                'notes' => 'Legacy invoice '.$inv->number,
                'terms' => $this->clean($inv->terms, 65000),
            ]);

            foreach (DB::connection('portal')->table('rise_pur_invoice_details')->where('pur_invoice', $inv->id)->orderBy('id')->get() as $pos => $d) {
                $bill->items()->create([
                    'organization_id' => $this->org,
                    'inventory_product_id' => $this->productId($d->item_code ?? null),
                    'description' => $this->clean($d->item_name ?? $d->description ?? null, 255) ?: ($this->clean($d->item_code, 255) ?: 'Item'),
                    'sku' => $this->clean($d->item_code, 80),
                    'unit' => null,
                    'quantity' => (float) ($d->quantity ?? 0),
                    'unit_cost' => (float) ($d->unit_price ?? 0),
                    'tax_rate' => 0,
                    'line_total' => (float) ($d->into_money ?? ((float) ($d->quantity ?? 0) * (float) ($d->unit_price ?? 0))),
                    'position' => $pos,
                ]);
            }

            // Payments — all legacy payments are recorded (approval_status 2 = approved).
            foreach (DB::connection('portal')->table('rise_pur_invoice_payment')->where('pur_invoice', $inv->id)->orderBy('id')->get() as $p) {
                $bill->payments()->create([
                    'organization_id' => $this->org,
                    'amount' => (float) ($p->amount ?? 0),
                    'payment_method' => $this->clean($p->paymentmode, 60),
                    'paid_on' => $this->date($p->date ?? null) ?? now()->toDateString(),
                    'reference' => $this->clean($p->transactionid, 120),
                    'note' => $this->clean($p->note, 500),
                    'approval_status' => BillPaymentApprovalStatus::Approved,
                    'requested_by' => $this->ownerFor($p->requester ?? null),
                    'recorded_by' => $this->ownerFor($p->requester ?? null),
                    'approved_by' => $this->fallbackOwnerId,
                    'approved_at' => now(),
                ]);
            }

            // Derive amount_paid + payment status from the (approved) payments.
            $billService->recomputePaymentStatus($bill->fresh());

            $this->putMap('pur_invoice', (int) $inv->id, (int) $bill->id, 'create');
            $created++;
        }

        $this->reportEntity('Bills', $rows->count(), $created, $matched, $skipped);
    }

    // ---- mapping helpers ---------------------------------------------------

    /** @return array<string,mixed> A generic PR/quotation line-item payload. */
    private function lineItem(object $d, ?string $label, int $pos): array
    {
        return [
            'organization_id' => $this->org,
            'inventory_product_id' => $this->productId($d->item_code ?? null),
            'description' => $this->clean($label, 255) ?: ($this->clean($d->item_code ?? null, 255) ?: 'Item'),
            'sku' => $this->clean($d->item_code ?? null, 80),
            'unit' => null,
            'quantity' => (float) ($d->quantity ?? 0),
            'unit_cost' => (float) ($d->unit_price ?? 0),
            'tax_rate' => is_numeric($d->tax_rate ?? null) ? (float) $d->tax_rate : 0,
            'line_total' => (float) ($d->into_money ?? ((float) ($d->quantity ?? 0) * (float) ($d->unit_price ?? 0))),
            'position' => $pos,
        ];
    }

    private function prStatus(int $s): PurchaseRequestStatus
    {
        return match ($s) {
            2 => PurchaseRequestStatus::Approved,
            3 => PurchaseRequestStatus::Rejected,
            4 => PurchaseRequestStatus::Cancelled,
            default => PurchaseRequestStatus::Draft,
        };
    }

    private function quoteStatus(int $s): QuotationStatus
    {
        return match ($s) {
            2 => QuotationStatus::Sent,
            3 => QuotationStatus::Rejected,
            4 => QuotationStatus::Accepted,
            5 => QuotationStatus::Expired,
            default => QuotationStatus::Draft,
        };
    }

    private function poStatus(int $approve, int $delivery): PurchaseOrderStatus
    {
        return match (true) {
            $approve === 4 => PurchaseOrderStatus::Cancelled,
            $delivery === 1 => PurchaseOrderStatus::Received,
            $delivery === 3 => PurchaseOrderStatus::PartiallyReceived,
            $approve === 2 => PurchaseOrderStatus::Approved,
            $approve === 1 => PurchaseOrderStatus::PendingApproval,
            default => PurchaseOrderStatus::Draft,
        };
    }

    /** Resolve a legacy item_code to one of our inventory products (by SKU), cached. */
    private function productId(?string $itemCode): ?int
    {
        $sku = trim((string) $itemCode);
        if ($sku === '') {
            return null;
        }
        if (! array_key_exists($sku, $this->productBySku)) {
            $this->productBySku[$sku] = Product::where('organization_id', $this->org)->where('sku', $sku)->value('id');
        }

        return $this->productBySku[$sku] ? (int) $this->productBySku[$sku] : null;
    }

    /** A valid 3-letter currency code, else USD (handles '0', country names, blanks). */
    private function cur($value): string
    {
        $v = strtoupper(trim((string) ($value ?? '')));

        return preg_match('/^[A-Z]{3}$/', $v) ? $v : 'USD';
    }

    private function date($value): ?string
    {
        $v = trim((string) ($value ?? ''));
        if ($v === '' || str_starts_with($v, '0000') || $v === 'null') {
            return null;
        }

        return substr($v, 0, 10);
    }

    /** Keep the supplier code unique within the org (append -2, -3, … on clash). */
    private function uniqueCode(string $code): string
    {
        $code = mb_substr($code, 0, 36);
        $base = $code;
        $n = 1;
        while (Supplier::withTrashed()->where('organization_id', $this->org)->where('code', $code)->exists()) {
            $code = $base.'-'.(++$n);
        }

        return $code;
    }

    private function ownerFor($portalUserId): int
    {
        return $this->getMap('user', (int) ($portalUserId ?? 0)) ?? $this->fallbackOwnerId;
    }

    private function clean($value, int $max): ?string
    {
        $v = trim((string) ($value ?? ''));
        if ($v === '') {
            return null;
        }

        return mb_strlen($v) > $max ? mb_substr($v, 0, $max) : $v;
    }

    private function ensureMapTable(): void
    {
        DB::connection('portal')->statement(
            'CREATE TABLE IF NOT EXISTS migration_map ('
            .'entity VARCHAR(40) NOT NULL, source_id INT NOT NULL, target_id BIGINT UNSIGNED NULL, '
            .'action VARCHAR(20) NULL, created_at DATETIME NULL, PRIMARY KEY(entity, source_id))'
        );
    }

    private function getMap(string $entity, ?int $srcId): ?int
    {
        if (! $srcId) {
            return null;
        }
        if (isset($this->mem[$entity][$srcId])) {
            return $this->mem[$entity][$srcId];
        }
        $v = DB::connection('portal')->table('migration_map')->where('entity', $entity)->where('source_id', $srcId)->value('target_id');

        return $v !== null ? ($this->mem[$entity][$srcId] = (int) $v) : null;
    }

    private function putMap(string $entity, int $srcId, int $targetId, string $action): void
    {
        $this->mem[$entity][$srcId] = $targetId;
        if (! $this->dry) {
            DB::connection('portal')->table('migration_map')->updateOrInsert(
                ['entity' => $entity, 'source_id' => $srcId],
                ['target_id' => $targetId, 'action' => $action, 'created_at' => now()],
            );
        }
    }

    private function dryId(): int
    {
        return --$this->dryCounter;
    }

    private function reportEntity(string $label, int $total, int $created, int $matched, int $skipped = 0): void
    {
        $verb = $this->dry ? 'would create' : 'created';
        $this->line(sprintf('  %-16s source=%-5d %s=%-5d matched(skip)=%-5d no-vendor(skip)=%-5d', $label, $total, $verb, $created, $matched, $skipped));
    }
}
