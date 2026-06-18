<?php

namespace App\Modules\AssetManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\AssetManagement\Enums\AssetStatus;
use App\Modules\AssetManagement\Models\Asset;
use App\Modules\AssetManagement\Models\MaintenanceRecord;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Asset::class);
        $orgId = $request->user()->organization_id;

        $operational = [AssetStatus::Assigned->value, AssetStatus::Deployed->value, AssetStatus::Active->value];
        $maintenance = [AssetStatus::UnderMaintenance->value, AssetStatus::InRepair->value];

        $recent = Asset::where('organization_id', $orgId)
            ->with('product:id,sku')
            ->latest('id')->limit(8)->get()
            ->map(fn (Asset $a) => [
                'id' => $a->id, 'asset_tag' => $a->asset_tag, 'name' => $a->name,
                'status_label' => $a->status->label(), 'status_color' => $a->status->color(),
                'location' => $a->location,
            ]);

        $upcoming = MaintenanceRecord::where('organization_id', $orgId)
            ->whereNotNull('next_due_at')
            ->whereDate('next_due_at', '>=', now()->toDateString())
            ->with('asset:id,asset_tag,name')
            ->orderBy('next_due_at')->limit(8)->get()
            ->map(fn (MaintenanceRecord $m) => [
                'id' => $m->id,
                'asset_id' => $m->asset_id,
                'asset' => $m->asset ? $m->asset->asset_tag.' · '.$m->asset->name : '—',
                'type_label' => $m->type->label(),
                'type_color' => $m->type->color(),
                'next_due_at' => $m->next_due_at?->toDateString(),
            ]);

        return Inertia::render('Assets/Dashboard', [
            'stats' => [
                'total' => Asset::where('organization_id', $orgId)->count(),
                'operational' => Asset::where('organization_id', $orgId)->whereIn('status', $operational)->count(),
                'in_maintenance' => Asset::where('organization_id', $orgId)->whereIn('status', $maintenance)->count(),
                'value' => round((float) Asset::where('organization_id', $orgId)->whereNotIn('status', [AssetStatus::Disposed->value])->sum('current_value'), 2),
            ],
            'recent' => $recent,
            'upcoming_maintenance' => $upcoming,
        ]);
    }
}
