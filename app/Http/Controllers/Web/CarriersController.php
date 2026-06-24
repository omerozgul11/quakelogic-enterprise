<?php

namespace App\Http\Controllers\Web;

use App\Enums\Carrier;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\ProposalMailing;
use App\Services\Mailings\CarrierRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Carriers section (/shipments/carriers): the connection status of each shipping
 * carrier. UPS is live today; FedEx and DHL are listed as "coming soon" until
 * their tracking clients are registered in TrackingClientFactory. Organisations
 * can also add their own custom carriers (e.g. freight companies), tracked
 * manually — stored in Organization.settings, not a carriers table.
 */
class CarriersController extends Controller
{
    public function __construct(private readonly CarrierRegistry $registry) {}

    public function index(Request $request): Response
    {
        $orgId = $request->user()->organization_id;
        $org = Organization::findOrFail($orgId);
        $ups = config('services.ups');
        $upsLive = (bool) ($ups['sync_enabled'] && $ups['client_id'] && $ups['client_secret']);
        $jbh = config('services.jbhunt');
        $jbhuntLive = (bool) ($jbh['sync_enabled'] && $jbh['client_id'] && $jbh['client_secret']);

        // Whether each live-capable carrier has real credentials configured.
        $credentialed = [
            Carrier::Ups->value => $upsLive,
            Carrier::JbHunt->value => $jbhuntLive,
        ];

        $counts = ProposalMailing::query()
            ->forOrganization($orgId)
            ->selectRaw('carrier, count(*) as c')
            ->groupBy('carrier')
            ->pluck('c', 'carrier');

        // Built-in carriers (the enum). A supported carrier is "live" only when its
        // credentials are set; UPS without creds falls back to a simulator ("test"),
        // J.B. Hunt without creds is integration-ready but awaiting keys ("available").
        $carriers = collect(Carrier::cases())->map(fn (Carrier $c) => [
            'key' => $c->value,
            'name' => $c->label(),
            'color' => $c->color(),
            'supported' => $c->supported(),
            'removable' => false,
            'status' => ! $c->supported()
                ? 'coming_soon'
                : (($credentialed[$c->value] ?? false)
                    ? 'live'
                    : ($c === Carrier::Ups ? 'test' : 'available')),
            'mailings' => (int) ($counts[$c->value] ?? 0),
        ]);

        // Custom carriers: those saved in the registry plus any already used on a
        // shipment that aren't built in. Removable only when nothing depends on them.
        $builtIn = collect(Carrier::cases())->map(fn ($c) => $c->value)->all();
        $custom = collect($this->registry->names($org))
            ->merge($counts->keys())
            ->map(fn ($n) => (string) $n)
            ->filter(fn ($n) => $n !== '' && ! in_array($n, $builtIn, true))
            ->unique(fn ($n) => mb_strtolower($n))
            ->map(fn ($name) => [
                'key' => $name,
                'name' => $name,
                'color' => 'gray',
                'supported' => false,
                'removable' => (int) ($counts[$name] ?? 0) === 0,
                'status' => 'custom',
                'mailings' => (int) ($counts[$name] ?? 0),
            ])->values();

        return Inertia::render('Shipments/Carriers', [
            'carriers' => $carriers->concat($custom)->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
        ]);

        $org = Organization::findOrFail($request->user()->organization_id);
        $added = $this->registry->add($org, $data['name']);

        if ($added === null) {
            return back()->with('warning', 'That carrier is already available.');
        }

        return back()->with('success', "Added {$added}. It's tracked manually — pick it when creating a shipment.");
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],      // current name
            'new_name' => ['required', 'string', 'max:50'],  // new name
        ]);

        $org = Organization::findOrFail($request->user()->organization_id);
        $from = trim($data['name']);
        $to = trim($data['new_name']);

        // Built-in carriers (UPS, J.B. Hunt, …) are code-defined — only custom ones rename.
        if ($this->registry->isBuiltIn($from)) {
            return back()->with('warning', "Built-in carriers can't be renamed.");
        }

        if (strcasecmp($from, $to) === 0) {
            return back(); // no change
        }

        $renamed = $this->registry->rename($org, $from, $to);
        if ($renamed === null) {
            return back()->with('warning', "Pick a name that isn't blank or an existing built-in carrier.");
        }

        // Re-point this org's shipments from the old name to the new one so none
        // are stranded (merges cleanly if the new name already had shipments).
        ProposalMailing::query()->forOrganization($org->id)
            ->where('carrier', $from)
            ->update(['carrier' => $renamed]);

        return back()->with('success', "Carrier renamed to {$renamed}.");
    }

    public function destroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
        ]);

        $org = Organization::findOrFail($request->user()->organization_id);

        // Don't strand shipments: only remove a carrier nothing is using.
        $inUse = ProposalMailing::query()->forOrganization($org->id)
            ->where('carrier', $data['name'])->exists();
        if ($inUse) {
            return back()->with('warning', 'That carrier has shipments — reassign or remove them first.');
        }

        $this->registry->remove($org, $data['name']);

        return back()->with('success', 'Carrier removed.');
    }
}
