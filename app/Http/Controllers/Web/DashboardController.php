<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Reporting\DashboardMetricsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardMetricsService $metrics) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $orgId = $user->organization_id;

        $data = $this->metrics->getUserDashboard($orgId, $user);

        return Inertia::render('Dashboard/Index', [
            'metrics' => $data,
            'canViewExecutiveDashboard' => $user->can('view executive dashboard'),
        ]);
    }

    public function executive(Request $request): Response
    {
        $this->authorize('view executive dashboard');

        $user = $request->user();
        $orgId = $user->organization_id;

        $data = $this->metrics->getExecutiveDashboard($orgId);

        return Inertia::render('Dashboard/Executive', [
            'metrics' => $data,
        ]);
    }
}
