<?php

namespace App\Modules\AssetManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Modules\AssetManagement\Enums\AssetStatus;
use App\Modules\AssetManagement\Enums\MaintenanceType;
use App\Modules\AssetManagement\Http\Requests\AssetRequest;
use App\Modules\AssetManagement\Models\Asset;
use App\Modules\AssetManagement\Models\MaintenanceRecord;
use App\Modules\AssetManagement\Services\AssetService;
use App\Modules\AssetManagement\Services\AssetTagService;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Inertia\Inertia;
use Inertia\Response;

class AssetController extends Controller
{
    public function __construct(
        private readonly AssetService $service,
        private readonly AssetTagService $tags,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Asset::class);
        $orgId = $request->user()->organization_id;

        $assets = Asset::where('organization_id', $orgId)
            ->with(['product:id,sku', 'assignee:id,name', 'company:id,name'])
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$s}%")
                ->orWhere('asset_tag', 'like', "%{$s}%")
                ->orWhere('serial_number', 'like', "%{$s}%")))
            ->when($request->status, fn ($q, $st) => $q->where('status', $st))
            ->latest('id')
            ->paginate(20)->withQueryString()
            ->through(fn (Asset $a) => [
                'id' => $a->id,
                'asset_tag' => $a->asset_tag,
                'name' => $a->name,
                'serial_number' => $a->serial_number,
                'status' => $a->status->value,
                'status_label' => $a->status->label(),
                'status_color' => $a->status->color(),
                'category' => $a->category,
                'location' => $a->location,
                'assignee' => $a->assignee?->name,
                'company' => $a->company?->name,
                'current_value' => $a->current_value !== null ? (float) $a->current_value : null,
                'currency' => $a->currency,
            ]);

        return Inertia::render('Assets/Index', [
            'assets' => $assets,
            'filters' => $request->only(['search', 'status']),
            'statuses' => AssetStatus::options(),
            'form' => $this->formData($request),
            'can' => ['manage' => $request->user()->can('manage assets')],
            'next_tag' => $this->tags->generate($orgId),
        ]);
    }

    public function show(Request $request, Asset $asset): Response
    {
        $this->authorize('view', $asset);

        $asset->load(['product:id,sku,name', 'assignee:id,name', 'company:id,name', 'creator:id,name']);
        $records = $asset->maintenanceRecords()->with('performer:id,name')->latest('performed_at')->latest('id')->get()
            ->map(fn (MaintenanceRecord $m) => [
                'id' => $m->id,
                'type' => $m->type->value,
                'type_label' => $m->type->label(),
                'type_color' => $m->type->color(),
                'status' => $m->status,
                'description' => $m->description,
                'cost' => $m->cost !== null ? (float) $m->cost : null,
                'performed_at' => $m->performed_at?->toDateString(),
                'next_due_at' => $m->next_due_at?->toDateString(),
                'by' => $m->performer?->name,
                'notes' => $m->notes,
            ]);

        return Inertia::render('Assets/Show', [
            'asset' => [
                'id' => $asset->id,
                'asset_tag' => $asset->asset_tag,
                'name' => $asset->name,
                'serial_number' => $asset->serial_number,
                'status' => $asset->status->value,
                'status_label' => $asset->status->label(),
                'status_color' => $asset->status->color(),
                'category' => $asset->category,
                'location' => $asset->location,
                'latitude' => $asset->latitude !== null ? (float) $asset->latitude : null,
                'longitude' => $asset->longitude !== null ? (float) $asset->longitude : null,
                'condition' => $asset->condition,
                'purchase_cost' => $asset->purchase_cost !== null ? (float) $asset->purchase_cost : null,
                'current_value' => $asset->current_value !== null ? (float) $asset->current_value : null,
                'currency' => $asset->currency,
                'purchased_at' => $asset->purchased_at?->toDateString(),
                'warranty_expires_at' => $asset->warranty_expires_at?->toDateString(),
                'warranty_active' => $asset->warrantyActive(),
                'deployed_at' => $asset->deployed_at?->toDateString(),
                'retired_at' => $asset->retired_at?->toDateString(),
                'notes' => $asset->notes,
                'product' => $asset->product ? ['id' => $asset->product->id, 'sku' => $asset->product->sku, 'name' => $asset->product->name] : null,
                'assignee' => $asset->assignee?->name,
                'company' => $asset->company?->name,
            ],
            'maintenance' => $records,
            'statuses' => AssetStatus::options(),
            'maintenance_types' => MaintenanceType::options(),
            'form' => $this->formData($request),
            'can' => ['manage' => $request->user()->can('manage assets'), 'maintain' => $request->user()->can('manage maintenance')],
        ]);
    }

    public function store(AssetRequest $request): RedirectResponse
    {
        $this->authorize('create', Asset::class);
        $user = $request->user();

        $asset = Asset::create([
            ...$request->validated(),
            'organization_id' => $user->organization_id,
            'created_by' => $user->id,
        ]);

        return redirect()->route('assets.registry.show', $asset)->with('success', 'Asset created.');
    }

    public function update(AssetRequest $request, Asset $asset): RedirectResponse
    {
        $this->authorize('update', $asset);
        $asset->update($request->validated());

        return back()->with('success', 'Asset updated.');
    }

    public function destroy(Request $request, Asset $asset): RedirectResponse
    {
        $this->authorize('delete', $asset);
        $asset->delete();

        return redirect()->route('assets.registry.index')->with('success', "Asset {$asset->asset_tag} deleted.");
    }

    public function transition(Request $request, Asset $asset): RedirectResponse
    {
        $this->authorize('update', $asset);
        $validated = $request->validate(['status' => ['required', new Enum(AssetStatus::class)]]);
        $this->service->transition($asset, AssetStatus::from($validated['status']));

        return back()->with('success', 'Asset status updated to '.$asset->status->label().'.');
    }

    public function commission(Request $request): RedirectResponse
    {
        $this->authorize('create', Asset::class);
        $user = $request->user();
        $orgId = $user->organization_id;

        $validated = $request->validate([
            'inventory_product_id' => ['required', Rule::exists('inventory_products', 'id')->where('organization_id', $orgId)->whereNull('deleted_at')],
            'inventory_warehouse_id' => ['required', Rule::exists('inventory_warehouses', 'id')->where('organization_id', $orgId)->whereNull('deleted_at')],
            'name' => ['nullable', 'string', 'max:200'],
            'serial_number' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', new Enum(AssetStatus::class)],
            'location' => ['nullable', 'string', 'max:200'],
            'company_id' => ['nullable', Rule::exists('companies', 'id')->where('organization_id', $orgId)],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('organization_id', $orgId)],
        ]);

        $product = Product::where('organization_id', $orgId)->findOrFail($validated['inventory_product_id']);
        $warehouse = Warehouse::where('organization_id', $orgId)->findOrFail($validated['inventory_warehouse_id']);

        try {
            $asset = $this->service->commissionFromInventory($product, $warehouse, $user->id, $validated);
        } catch (InsufficientStockException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('assets.registry.show', $asset)->with('success', "Asset {$asset->asset_tag} commissioned from stock.");
    }

    public function storeMaintenance(Request $request, Asset $asset): RedirectResponse
    {
        $this->authorize('maintain', $asset);
        $validated = $request->validate([
            'type' => ['required', new Enum(MaintenanceType::class)],
            'status' => ['nullable', 'string', 'in:scheduled,in_progress,completed'],
            'description' => ['required', 'string', 'max:1000'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'performed_at' => ['nullable', 'date'],
            'next_due_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $this->service->logMaintenance($asset, $validated, $request->user()->id);

        return back()->with('success', 'Maintenance record added.');
    }

    public function destroyMaintenance(Request $request, Asset $asset, MaintenanceRecord $record): RedirectResponse
    {
        $this->authorize('maintain', $asset);
        abort_unless($record->asset_id === $asset->id, 404);
        $record->delete();

        return back()->with('success', 'Maintenance record removed.');
    }

    /** @return array<string,mixed> */
    private function formData(Request $request): array
    {
        $orgId = $request->user()->organization_id;

        return [
            'products' => Product::where('organization_id', $orgId)->where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name']),
            'warehouses' => Warehouse::where('organization_id', $orgId)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'companies' => Company::where('organization_id', $orgId)->orderBy('name')->get(['id', 'name']),
            'users' => User::where('organization_id', $orgId)->orderBy('name')->get(['id', 'name']),
        ];
    }
}
