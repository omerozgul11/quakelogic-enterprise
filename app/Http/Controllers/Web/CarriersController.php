<?php

namespace App\Http\Controllers\Web;

use App\Enums\Carrier;
use App\Http\Controllers\Controller;
use App\Models\ProposalMailing;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Carriers section (/shipments/carriers): the connection status of each shipping
 * carrier. UPS is live today; FedEx and DHL are listed as "coming soon" until
 * their tracking clients are registered in TrackingClientFactory.
 */
class CarriersController extends Controller
{
    public function index(Request $request): Response
    {
        $orgId = $request->user()->organization_id;
        $ups = config('services.ups');
        $upsLive = (bool) ($ups['sync_enabled'] && $ups['client_id'] && $ups['client_secret']);

        $counts = ProposalMailing::query()
            ->forOrganization($orgId)
            ->selectRaw('carrier, count(*) as c')
            ->groupBy('carrier')
            ->pluck('c', 'carrier');

        $carriers = collect(Carrier::cases())->map(fn (Carrier $c) => [
            'key' => $c->value,
            'name' => $c->label(),
            'color' => $c->color(),
            'supported' => $c->supported(),
            'status' => ! $c->supported()
                ? 'coming_soon'
                : ($c === Carrier::Ups ? ($upsLive ? 'live' : 'test') : 'available'),
            'mailings' => (int) ($counts[$c->value] ?? 0),
        ])->values();

        return Inertia::render('Shipments/Carriers', [
            'carriers' => $carriers,
        ]);
    }
}
