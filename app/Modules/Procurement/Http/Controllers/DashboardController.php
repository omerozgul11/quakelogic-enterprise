<?php

namespace App\Modules\Procurement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Procurement\Enums\PurchaseOrderStatus;
use App\Modules\Procurement\Enums\SupplierStatus;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\Supplier;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PurchaseOrder::class);
        $orgId = $request->user()->organization_id;

        $openStatuses = [
            PurchaseOrderStatus::Approved->value,
            PurchaseOrderStatus::Sent->value,
            PurchaseOrderStatus::PartiallyReceived->value,
        ];

        $recent = PurchaseOrder::where('organization_id', $orgId)
            ->with('supplier:id,name')
            ->latest('id')->limit(8)->get()
            ->map(fn (PurchaseOrder $po) => $this->row($po));

        $pendingApproval = PurchaseOrder::where('organization_id', $orgId)
            ->where('status', PurchaseOrderStatus::PendingApproval->value)
            ->with('supplier:id,name')
            ->latest('id')->limit(8)->get()
            ->map(fn (PurchaseOrder $po) => $this->row($po));

        return Inertia::render('Procurement/Dashboard', [
            'stats' => [
                'suppliers' => Supplier::where('organization_id', $orgId)->count(),
                'active_suppliers' => Supplier::where('organization_id', $orgId)->where('status', SupplierStatus::Active->value)->count(),
                'open_pos' => PurchaseOrder::where('organization_id', $orgId)->whereIn('status', $openStatuses)->count(),
                'pending_approval' => PurchaseOrder::where('organization_id', $orgId)->where('status', PurchaseOrderStatus::PendingApproval->value)->count(),
                'open_value' => round((float) PurchaseOrder::where('organization_id', $orgId)->whereIn('status', $openStatuses)->sum('total'), 2),
            ],
            'recent' => $recent,
            'pending_approval_list' => $pendingApproval,
        ]);
    }

    /** @return array<string,mixed> */
    private function row(PurchaseOrder $po): array
    {
        return [
            'id' => $po->id,
            'number' => $po->number,
            'supplier' => $po->supplier?->name,
            'status_label' => $po->status->label(),
            'status_color' => $po->status->color(),
            'total' => (float) $po->total,
            'currency' => $po->currency,
            'order_date' => $po->order_date?->toDateString(),
        ];
    }
}
