<?php

namespace App\Modules\Manufacturing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Manufacturing\Enums\BomStatus;
use App\Modules\Manufacturing\Enums\WorkOrderStatus;
use App\Modules\Manufacturing\Models\Bom;
use App\Modules\Manufacturing\Models\WorkOrder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', WorkOrder::class);
        $orgId = $request->user()->organization_id;

        $openStatuses = [WorkOrderStatus::Draft->value, WorkOrderStatus::Released->value, WorkOrderStatus::InProgress->value];

        $open = WorkOrder::where('organization_id', $orgId)
            ->whereIn('status', $openStatuses)
            ->with('product:id,sku,name')
            ->latest('id')->limit(8)->get()
            ->map(fn (WorkOrder $w) => $this->row($w));

        $recent = WorkOrder::where('organization_id', $orgId)
            ->with('product:id,sku,name')
            ->latest('id')->limit(8)->get()
            ->map(fn (WorkOrder $w) => $this->row($w));

        return Inertia::render('Manufacturing/Dashboard', [
            'stats' => [
                'boms' => Bom::where('organization_id', $orgId)->count(),
                'active_boms' => Bom::where('organization_id', $orgId)->where('status', BomStatus::Active->value)->count(),
                'open_work_orders' => WorkOrder::where('organization_id', $orgId)->whereIn('status', $openStatuses)->count(),
                'completed' => WorkOrder::where('organization_id', $orgId)->where('status', WorkOrderStatus::Completed->value)->count(),
            ],
            'open' => $open,
            'recent' => $recent,
        ]);
    }

    /** @return array<string,mixed> */
    private function row(WorkOrder $w): array
    {
        return [
            'id' => $w->id,
            'number' => $w->number,
            'product' => $w->product ? $w->product->sku.' · '.$w->product->name : null,
            'status_label' => $w->status->label(),
            'status_color' => $w->status->color(),
            'quantity_planned' => (float) $w->quantity_planned,
            'quantity_produced' => (float) $w->quantity_produced,
        ];
    }
}
