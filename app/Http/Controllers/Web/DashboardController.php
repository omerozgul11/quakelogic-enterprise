<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Reporting\DashboardMetricsService;
use App\Services\Reporting\ExchangeRateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardMetricsService $metrics,
        private readonly ExchangeRateService $exchangeRates,
    ) {}

    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $orgId = $user->organization_id;

        // Honour the user's preferred default dashboard view.
        $defaultView = $user->notification_preferences['dashboard']['default_view'] ?? 'personal';
        if ($defaultView === 'executive' && !$request->boolean('home') && $user->can('view executive dashboard')) {
            return redirect()->route('dashboard.executive');
        }

        $data = $this->metrics->getUserDashboard($orgId, $user);

        return Inertia::render('Dashboard/Index', [
            'metrics' => $data,
            'canViewExecutiveDashboard' => $user->can('view executive dashboard'),
            'exchangeRates' => $this->exchangeRates->dailyRates(),
            'eurUsdThreshold' => (float) (data_get($user->notification_preferences, 'dashboard.eur_usd_threshold') ?? 1.14),
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
            'exchangeRates' => $this->exchangeRates->dailyRates(),
        ]);
    }
}
