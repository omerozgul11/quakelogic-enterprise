<?php

namespace App\Modules\Procurement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Procurement\Enums\SupplierStatus;
use App\Modules\Procurement\Http\Requests\SupplierRequest;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\Supplier;
use App\Modules\Procurement\Models\SupplierContact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $supplier->load(['contacts' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('name')]);

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
            'can' => ['manage' => $request->user()->can('manage suppliers')],
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
