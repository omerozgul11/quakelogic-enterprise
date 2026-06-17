<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Opportunities\OpportunityOversightService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The executive "Opportunity Command Center": one dashboard showing every
 * opportunity discovered, who owns it, who hasn't acted, what's at risk, who's
 * overloaded, and what to reassign. Gated to executives/admins.
 */
class OpportunityOversightController extends Controller
{
    public function index(Request $request, OpportunityOversightService $oversight): Response
    {
        abort_unless($request->user()->can('view executive dashboard'), 403);

        return Inertia::render('Dashboard/Oversight', [
            'summary' => $oversight->summary($request->user()->organization_id),
            'generatedAt' => now()->toIso8601String(),
        ]);
    }
}
