<?php

namespace App\Http\Controllers\Web;

use App\Enums\Carrier;
use App\Http\Controllers\Controller;
use App\Models\DhlPushSubscription;
use App\Models\Organization;
use App\Models\ProposalMailing;
use App\Services\Mailings\CarrierProfileService;
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
    public function __construct(
        private readonly CarrierRegistry $registry,
        private readonly CarrierProfileService $profiles,
    ) {}

    public function index(Request $request): Response
    {
        $orgId = $request->user()->organization_id;
        $org = Organization::findOrFail($orgId);
        $ups = config('services.ups');
        $upsLive = (bool) ($ups['sync_enabled'] && $ups['client_id'] && $ups['client_secret']);
        $jbh = config('services.jbhunt');
        $jbhuntLive = (bool) ($jbh['sync_enabled'] && $jbh['client_id'] && $jbh['client_secret']);
        // DHL is live once the DHL-API-Key is present (powers both pull tracking and push).
        $dhlLive = (bool) config('services.dhl.api_key');

        // Whether each live-capable carrier has real credentials configured.
        $credentialed = [
            Carrier::Ups->value => $upsLive,
            Carrier::JbHunt->value => $jbhuntLive,
            Carrier::Dhl->value => $dhlLive,
        ];

        // Carriers that track live WITHOUT credentials (R+L's public tracing page
        // needs no key; an optional RL_API_KEY just upgrades it to the REST API).
        $liveByDefault = [Carrier::RlCarriers->value];

        $counts = ProposalMailing::query()
            ->forOrganization($orgId)
            ->selectRaw('carrier, count(*) as c')
            ->groupBy('carrier')
            ->pluck('c', 'carrier');

        // Built-in carriers the org has "removed" (hidden). Still shown in their
        // own section so they can be restored. A hidden carrier that somehow has
        // shipments stays visible in the main list so nothing is stranded.
        $hidden = collect($this->registry->hidden($org))
            ->reject(fn ($key) => (int) ($counts[$key] ?? 0) > 0)
            ->values()
            ->all();

        // Built-in carriers (the enum). A supported carrier is "live" only when its
        // credentials are set; UPS without creds falls back to a simulator ("test"),
        // J.B. Hunt without creds is integration-ready but awaiting keys ("available").
        $carriers = collect(Carrier::cases())
            ->reject(fn (Carrier $c) => in_array($c->value, $hidden, true))
            ->map(function (Carrier $c) use ($credentialed, $liveByDefault, $counts, $org) {
            $profile = $this->profiles->get($org, $c->value);

            return [
                'key' => $c->value,
                'name' => $c->label(),
                'color' => $c->color(),
                'supported' => $c->supported(),
                'removable' => (int) ($counts[$c->value] ?? 0) === 0,
                'status' => ! $c->supported()
                    ? 'coming_soon'
                    : (($credentialed[$c->value] ?? false) || in_array($c->value, $liveByDefault, true)
                        ? 'live'
                        : ($c === Carrier::Ups ? 'test' : 'available')),
                'mailings' => (int) ($counts[$c->value] ?? 0),
                'import_number' => $profile['import_number'],
                'export_number' => $profile['export_number'],
                // Org override wins; otherwise the carrier's default sign-in page.
                'login_url' => $profile['login_url'] !== '' ? $profile['login_url'] : ($c->loginUrl() ?? ''),
                'login_url_override' => $profile['login_url'],
                'default_login_url' => $c->loginUrl(),
            ];
        });

        // Custom carriers: those saved in the registry plus any already used on a
        // shipment that aren't built in. Removable only when nothing depends on them.
        $builtIn = collect(Carrier::cases())->map(fn ($c) => $c->value)->all();
        $custom = collect($this->registry->names($org))
            ->merge($counts->keys())
            ->map(fn ($n) => (string) $n)
            ->filter(fn ($n) => $n !== '' && ! in_array($n, $builtIn, true))
            ->unique(fn ($n) => mb_strtolower($n))
            ->map(function ($name) use ($counts, $org) {
                $profile = $this->profiles->get($org, $name);

                return [
                    'key' => $name,
                    'name' => $name,
                    'color' => 'gray',
                    'supported' => false,
                    'removable' => (int) ($counts[$name] ?? 0) === 0,
                    'status' => 'custom',
                    'mailings' => (int) ($counts[$name] ?? 0),
                    'import_number' => $profile['import_number'],
                    'export_number' => $profile['export_number'],
                    'login_url' => $profile['login_url'],
                    'login_url_override' => $profile['login_url'],
                    'default_login_url' => null,
                ];
            })->values();

        $hiddenCarriers = collect(Carrier::cases())
            ->filter(fn (Carrier $c) => in_array($c->value, $hidden, true))
            ->map(fn (Carrier $c) => ['key' => $c->value, 'name' => $c->label(), 'color' => $c->color()])
            ->values();

        return Inertia::render('Shipments/Carriers', [
            'carriers' => $carriers->concat($custom)->values(),
            'hiddenCarriers' => $hiddenCarriers,
            'dhl' => $this->dhlPanel($orgId, $dhlLive),
        ]);
    }

    /**
     * DHL push-integration status for the Carriers page: whether the API key +
     * webhook token are configured, the webhook URL to register with DHL, and this
     * org's live push subscriptions.
     *
     * @return array<string,mixed>
     */
    private function dhlPanel(int $orgId, bool $apiConfigured): array
    {
        $token = config('services.dhl.push.webhook_token');

        $subscriptions = DhlPushSubscription::forOrganization($orgId)
            ->latest()
            ->get()
            ->map(fn (DhlPushSubscription $s) => [
                'ulid' => $s->ulid,
                'type' => $s->type,
                'tracking_number' => $s->tracking_number,
                'account_number' => $s->account_number,
                'status' => $s->status,
                'created_at' => optional($s->created_at)->toDateString(),
            ])->values();

        return [
            'apiConfigured' => $apiConfigured,
            'pushConfigured' => (bool) $token,
            'webhookUrl' => $token
                ? (config('services.dhl.push.webhook_url') ?: url('/api/dhl/webhook/'.$token))
                : null,
            'subscriptions' => $subscriptions,
        ];
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

        $this->profiles->rename($org, $from, $renamed);

        return back()->with('success', "Carrier renamed to {$renamed}.");
    }

    /**
     * Save a carrier's internal account details — import/export numbers and the
     * login URL — for any carrier (built-in or custom). Custom carriers can also
     * be renamed here (built-ins can't). Org-scoped via the carrier key.
     */
    public function updateProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:50'],
            'new_name' => ['nullable', 'string', 'max:50'],
            'import_number' => ['nullable', 'string', 'max:50'],
            'export_number' => ['nullable', 'string', 'max:50'],
            'login_url' => ['nullable', 'string', 'max:255'],
        ]);

        $org = Organization::findOrFail($request->user()->organization_id);
        $key = trim($data['key']);

        $enum = Carrier::tryFrom(mb_strtolower($key));
        $isBuiltIn = $enum !== null || $this->registry->isBuiltIn($key);
        $isCustom = collect($this->registry->names($org))->contains(fn ($n) => strcasecmp($n, $key) === 0);
        abort_unless($isBuiltIn || $isCustom, 404);

        // Canonical storage key: the enum value for built-ins, the name for custom.
        $storageKey = $enum?->value ?? $key;

        // Optional rename (custom carriers only).
        $newName = trim((string) ($data['new_name'] ?? ''));
        if ($isCustom && ! $isBuiltIn && $newName !== '' && strcasecmp($newName, $key) !== 0) {
            $renamed = $this->registry->rename($org, $key, $newName);
            if ($renamed === null) {
                return back()->with('warning', "Pick a name that isn't blank or an existing built-in carrier.");
            }
            ProposalMailing::query()->forOrganization($org->id)
                ->where('carrier', $key)->update(['carrier' => $renamed]);
            $this->profiles->rename($org, $key, $renamed);
            $storageKey = $renamed;
        }

        $this->profiles->set($org, $storageKey, [
            'import_number' => $data['import_number'] ?? '',
            'export_number' => $data['export_number'] ?? '',
            'login_url' => $data['login_url'] ?? '',
        ]);

        return back()->with('success', 'Carrier details saved.');
    }

    /**
     * Remove a carrier. Custom carriers are deleted outright; built-in carriers
     * (code-defined) are hidden for this org and can be restored. Either way it's
     * blocked while shipments still use the carrier, so nothing is stranded.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:50'],
        ]);

        $org = Organization::findOrFail($request->user()->organization_id);
        $key = trim($data['key']);

        $inUse = ProposalMailing::query()->forOrganization($org->id)
            ->where('carrier', $key)->exists();
        if ($inUse) {
            return back()->with('warning', 'That carrier has shipments — reassign or remove them first.');
        }

        if ($this->registry->isBuiltIn($key)) {
            $enum = Carrier::tryFrom(mb_strtolower($key));
            $this->registry->hide($org, $enum?->value ?? $key);

            return back()->with('success', 'Carrier removed. Restore it anytime from "Removed carriers" below.');
        }

        $this->registry->remove($org, $key);
        $this->profiles->forget($org, $key);

        return back()->with('success', 'Carrier removed.');
    }

    /** Bring back a hidden built-in carrier. */
    public function restore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:50'],
        ]);

        $org = Organization::findOrFail($request->user()->organization_id);
        $this->registry->unhide($org, $data['key']);

        return back()->with('success', 'Carrier restored.');
    }
}
