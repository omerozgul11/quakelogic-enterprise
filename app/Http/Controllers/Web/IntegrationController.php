<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Integration;
use App\Models\SamImport;
use App\Services\BidSources\SamGov\SamGovImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationController extends Controller
{
    public function index(Request $request): Response
    {
        $integrations = Integration::where('organization_id', $request->user()->organization_id)
            ->get(['id', 'name', 'type', 'status', 'last_sync_at as last_synced_at', 'created_at']);

        $lastImport = SamImport::where('organization_id', $request->user()->organization_id)
            ->latest()->first();

        return Inertia::render('Integrations/Index', [
            'integrations' => $integrations,
            'samGov' => [
                'connected' => !empty(config('integrations.sam_gov.api_key')),
                'sync_enabled' => (bool) config('integrations.sam_gov.sync_enabled'),
                'last_import' => $lastImport?->completed_at?->toIso8601String(),
                'last_stats' => $lastImport ? [
                    'imported' => (int) $lastImport->imported_records,
                    'updated' => (int) $lastImport->updated_records,
                ] : null,
            ],
        ]);
    }

    public function sync(Request $request, string $type, SamGovImportService $samImport): RedirectResponse
    {
        if ($type !== 'sam_gov') {
            return back()->with('warning', 'Live sync is not available for this integration yet.');
        }

        if (empty(config('integrations.sam_gov.api_key'))) {
            return back()->with('error', 'SAM.gov is not connected — no API key is configured.');
        }

        $filters = $request->validate([
            'naics_codes' => 'nullable|array',
            'keywords' => 'nullable|string',
        ]);
        $filters['max_pages'] = 2;

        $stats = $samImport->import($request->user()->organization, $filters, $request->user());

        return back()->with('success',
            "SAM.gov sync complete: {$stats['imported']} new, {$stats['updated']} updated, {$stats['errors']} errors."
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:sam_gov,bidprime,govwin,email_smtp,email_gmail,email_microsoft',
            'credentials' => 'required|array',
        ]);

        Integration::create([
            'organization_id' => $request->user()->organization_id,
            'name' => $validated['name'],
            'type' => $validated['type'],
            'status' => 'active',
            'encrypted_credentials' => Crypt::encrypt($validated['credentials']),
        ]);

        return back()->with('success', 'Integration added.');
    }

    public function destroy(Request $request, Integration $integration): RedirectResponse
    {
        abort_unless($integration->organization_id === $request->user()->organization_id, 403);
        $integration->delete();
        return back()->with('success', 'Integration removed.');
    }
}
