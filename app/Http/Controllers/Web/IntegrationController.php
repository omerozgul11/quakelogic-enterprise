<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Integration;
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

        return Inertia::render('Integrations/Index', ['integrations' => $integrations]);
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
