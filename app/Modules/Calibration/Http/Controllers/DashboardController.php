<?php

namespace App\Modules\Calibration\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Calibration\Models\CalibrationCertificate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CalibrationCertificate::class);
        $orgId = $request->user()->organization_id;
        $today = now()->toDateString();
        $in30 = now()->addDays(30)->toDateString();

        $base = fn () => CalibrationCertificate::where('organization_id', $orgId);

        $upcoming = $base()
            ->whereNotNull('due_at')
            ->with(['asset:id,asset_tag,name', 'product:id,sku'])
            ->orderBy('due_at')->limit(10)->get()
            ->map(fn (CalibrationCertificate $c) => [
                'id' => $c->id,
                'certificate_number' => $c->certificate_number,
                'subject' => $c->asset ? $c->asset->asset_tag.' · '.$c->asset->name : ($c->product?->sku ?? '—'),
                'due_at' => $c->due_at?->toDateString(),
                'overdue' => $c->isOverdue(),
                'result_color' => $c->result->color(),
                'result_label' => $c->result->label(),
            ]);

        return Inertia::render('Calibration/Dashboard', [
            'stats' => [
                'total' => $base()->count(),
                'overdue' => $base()->whereNotNull('due_at')->whereDate('due_at', '<', $today)->count(),
                'due_soon' => $base()->whereNotNull('due_at')->whereBetween('due_at', [$today, $in30])->count(),
                'this_year' => $base()->whereYear('calibrated_at', now()->year)->count(),
            ],
            'upcoming' => $upcoming,
        ]);
    }
}
