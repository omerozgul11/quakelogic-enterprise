<?php

namespace App\Modules\Procurement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Procurement\Enums\SupplierStatus;
use App\Modules\Procurement\Http\Requests\SupplierRequest;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\Supplier;
use App\Modules\Procurement\Models\SupplierContact;
use App\Modules\Procurement\Models\SupplierProduct;
use App\Modules\Procurement\Services\SupplierPriceListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Supplier::class);
        $orgId = $request->user()->organization_id;

        $suppliers = Supplier::where('organization_id', $orgId)
            ->withCount('purchaseOrders')
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$s}%")
                ->orWhere('code', 'like', "%{$s}%")
                ->orWhere('category', 'like', "%{$s}%")))
            ->when($request->status, fn ($q, $st) => $q->where('status', $st))
            ->orderBy('name')
            ->paginate(20)->withQueryString()
            ->through(fn (Supplier $s) => [
                'id' => $s->id,
                'code' => $s->code,
                'name' => $s->name,
                'category' => $s->category,
                'status_label' => $s->status->label(),
                'status_color' => $s->status->color(),
                'email' => $s->email,
                'phone' => $s->phone,
                'payment_terms' => $s->payment_terms,
                'purchase_orders_count' => $s->purchase_orders_count,
            ]);

        return Inertia::render('Procurement/Suppliers/Index', [
            'suppliers' => $suppliers,
            'filters' => $request->only(['search', 'status']),
            'statuses' => SupplierStatus::options(),
            'can' => ['manage' => $request->user()->can('manage suppliers')],
        ]);
    }

    public function show(Request $request, Supplier $supplier): Response
    {
        $this->authorize('view', $supplier);

        $supplier->load([
            'contacts' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('name'),
            'attachments.uploader:id,name',
            'products' => fn ($q) => $q->orderByDesc('last_imported_at')->orderByDesc('id'),
            'products.product:id,sku,name,unit_cost,unit_price,currency,is_active',
        ]);

        $orders = $supplier->purchaseOrders()
            ->latest('id')->limit(20)->get()
            ->map(fn (PurchaseOrder $po) => [
                'id' => $po->id,
                'number' => $po->number,
                'status_label' => $po->status->label(),
                'status_color' => $po->status->color(),
                'total' => (float) $po->total,
                'currency' => $po->currency,
                'order_date' => $po->order_date?->toDateString(),
            ]);

        return Inertia::render('Procurement/Suppliers/Show', [
            'supplier' => [
                'id' => $supplier->id,
                'code' => $supplier->code,
                'name' => $supplier->name,
                'category' => $supplier->category,
                'status' => $supplier->status->value,
                'status_label' => $supplier->status->label(),
                'status_color' => $supplier->status->color(),
                'email' => $supplier->email,
                'phone' => $supplier->phone,
                'website' => $supplier->website,
                'address_line1' => $supplier->address_line1,
                'city' => $supplier->city,
                'state' => $supplier->state,
                'postal_code' => $supplier->postal_code,
                'country' => $supplier->country,
                'payment_terms' => $supplier->payment_terms,
                'currency' => $supplier->currency,
                'tax_id' => $supplier->tax_id,
                'lead_time_days' => $supplier->lead_time_days,
                'rating' => $supplier->rating,
                'notes' => $supplier->notes,
                'contacts' => $supplier->contacts->map(fn (SupplierContact $c) => [
                    'id' => $c->id, 'name' => $c->name, 'title' => $c->title,
                    'email' => $c->email, 'phone' => $c->phone, 'is_primary' => $c->is_primary,
                    'portal_enabled' => (bool) $c->portal_enabled,
                    'portal_last_login_at' => $c->portal_last_login_at?->toIso8601String(),
                ]),
            ],
            'orders' => $orders,
            'spend' => round((float) $supplier->purchaseOrders()->sum('total'), 2),
            'statuses' => SupplierStatus::options(),
            'products' => $supplier->products->map(fn (SupplierProduct $sp) => [
                'id' => $sp->id,
                'supplier_sku' => $sp->supplier_sku,
                'supplier_price' => $sp->supplier_price !== null ? (float) $sp->supplier_price : null,
                'currency' => $sp->currency,
                'last_imported_at' => $sp->last_imported_at?->toDateString(),
                'product' => $sp->product ? [
                    'id' => $sp->product->id,
                    'sku' => $sp->product->sku,
                    'name' => $sp->product->name,
                    'unit_cost' => (float) $sp->product->unit_cost,
                    'currency' => $sp->product->currency,
                    'is_active' => (bool) $sp->product->is_active,
                ] : null,
            ])->values(),
            'attachments' => AttachmentController::serialize($supplier),
            'can' => ['manage' => $request->user()->can('manage suppliers')],
        ]);
    }

    private const PRICE_LIST_MIMES = 'application/pdf,image/jpeg,image/png,image/heic,image/heif,'
        .'application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,'
        .'text/plain,text/csv';

    /**
     * Parse a dropped price list / product sheet into matched line items for
     * review. The raw file is also kept as a supplier attachment. Returns JSON —
     * the page shows a review modal before anything is written to inventory.
     */
    public function priceListExtract(Request $request, Supplier $supplier, SupplierPriceListService $service): JsonResponse
    {
        $this->authorize('update', $supplier);

        $request->validate([
            'file' => ['required', 'file', 'max:25600', 'mimetypes:'.self::PRICE_LIST_MIMES],
        ]);

        $file = $request->file('file');
        $result = $service->parse($file, $supplier);   // read the upload before we move it
        $this->storeAttachment($supplier, $file, $request->user()->id);

        return response()->json($result);
    }

    /**
     * Apply the reviewed price-list lines: update matched products' cost, create
     * new products for unmatched lines, and upsert the supplier↔product links.
     */
    public function priceListApply(Request $request, Supplier $supplier, SupplierPriceListService $service): RedirectResponse
    {
        $this->authorize('update', $supplier);

        $data = $request->validate([
            'lines' => ['required', 'array', 'max:2000'],
            'lines.*.action' => ['required', Rule::in(['update', 'create', 'skip'])],
            'lines.*.product_id' => ['nullable', 'integer'],
            'lines.*.supplier_sku' => ['nullable', 'string', 'max:255'],
            'lines.*.name' => ['nullable', 'string', 'max:255'],
            'lines.*.price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.currency' => ['nullable', 'string', 'max:3'],
        ]);

        $summary = $service->apply($supplier, $data['lines'], $request->user());

        return back()->with('success', sprintf(
            'Price list applied — %d updated, %d created, %d linked, %d skipped.',
            $summary['updated'], $summary['created'], $summary['linked'], $summary['skipped'],
        ));
    }

    /** Persist the raw dropped file as a supplier attachment (record-keeping). */
    private function storeAttachment(Supplier $supplier, \Illuminate\Http\UploadedFile $file, int $userId): void
    {
        $stored = (string) Str::ulid().'.'.($file->getClientOriginalExtension() ?: 'bin');
        $path = $file->storeAs("procurement/attachments/suppliers/{$supplier->id}", $stored, 'local');

        $supplier->attachments()->create([
            'organization_id' => $supplier->organization_id,
            'uploaded_by' => $userId,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
    }

    public function store(SupplierRequest $request): RedirectResponse
    {
        $this->authorize('create', Supplier::class);
        $user = $request->user();

        $supplier = Supplier::create([
            ...$request->validated(),
            'organization_id' => $user->organization_id,
            'created_by' => $user->id,
            'owner_id' => $user->id,
        ]);

        return redirect()->route('procurement.suppliers.show', $supplier)->with('success', 'Supplier created.');
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $this->authorize('update', $supplier);
        $supplier->update($request->validated());

        return back()->with('success', 'Supplier updated.');
    }

    public function destroy(Request $request, Supplier $supplier): RedirectResponse
    {
        $this->authorize('delete', $supplier);

        if ($supplier->purchaseOrders()->exists()) {
            return back()->with('error', 'Cannot delete a supplier that has purchase orders.');
        }

        $name = $supplier->name;
        $supplier->delete();

        return redirect()->route('procurement.suppliers.index')->with('success', "Supplier \"{$name}\" deleted.");
    }

    public function storeContact(Request $request, Supplier $supplier): RedirectResponse
    {
        $this->authorize('update', $supplier);
        $supplier->contacts()->create([
            ...$this->validateContact($request),
            'organization_id' => $supplier->organization_id,
        ]);

        return back()->with('success', 'Contact added.');
    }

    public function updateContact(Request $request, Supplier $supplier, SupplierContact $contact): RedirectResponse
    {
        $this->authorize('update', $supplier);
        abort_unless($contact->procurement_supplier_id === $supplier->id, 404);
        $contact->update($this->validateContact($request));

        return back()->with('success', 'Contact updated.');
    }

    /**
     * Grant or revoke vendor-portal access for a contact, and (re)set the portal
     * password. The password is bcrypt-hashed; disabling never touches it.
     */
    public function contactPortal(Request $request, Supplier $supplier, SupplierContact $contact): RedirectResponse
    {
        $this->authorize('update', $supplier);
        abort_unless($contact->procurement_supplier_id === $supplier->id, 404);

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
        ]);

        if (! $data['enabled']) {
            $contact->forceFill(['portal_enabled' => false])->save();

            return back()->with('success', 'Vendor portal access disabled.');
        }

        if (! $contact->email) {
            return back()->with('error', 'Add an email to this contact before enabling portal access.');
        }
        if (empty($data['password']) && empty($contact->portal_password)) {
            return back()->with('error', 'Set a password to enable portal access.');
        }

        $attrs = ['portal_enabled' => true];
        if (! empty($data['password'])) {
            $attrs['portal_password'] = \Illuminate\Support\Facades\Hash::make($data['password']);
        }
        $contact->forceFill($attrs)->save();

        return back()->with('success', 'Vendor portal access enabled.');
    }

    public function destroyContact(Request $request, Supplier $supplier, SupplierContact $contact): RedirectResponse
    {
        $this->authorize('update', $supplier);
        abort_unless($contact->procurement_supplier_id === $supplier->id, 404);
        $contact->delete();

        return back()->with('success', 'Contact removed.');
    }

    /** @return array<string,mixed> */
    private function validateContact(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'title' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'is_primary' => ['boolean'],
        ]);
    }
}
