<?php

namespace App\Http\Controllers\Web;

use App\Enums\MailingStatus;
use App\Http\Controllers\Controller;
use App\Models\ProposalMailing;
use App\Models\ProposalSubmission;
use App\Services\Mailings\MailingTrackingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Shipments section (/shipments): UPS delivery tracking for mailed proposals.
 * Lives inside the proposals app, gated by the `access shipments` permission,
 * reusing the shared User/Organization/ProposalSubmission/Agency models.
 */
class MailingController extends Controller
{
    public function __construct(private readonly MailingTrackingService $tracking) {}

    public function dashboard(Request $request): Response
    {
        $orgId = $request->user()->organization_id;
        $base = fn () => ProposalMailing::query()->forOrganization($orgId);
        $deliveredOnTime = (clone $base())->where('status', MailingStatus::Delivered->value)->where('on_time', true)->count();
        $deliveredLate = (clone $base())->where('status', MailingStatus::Delivered->value)->where('on_time', false)->count();
        $totalDelivered = $deliveredOnTime + $deliveredLate;

        return Inertia::render('Shipments/Index', [
            'stats' => [
                'active' => (clone $base())->active()->count(),
                'at_risk' => (clone $base())->active()
                    ->whereNotNull('deadline')->whereNotNull('scheduled_delivery')
                    ->whereColumn('scheduled_delivery', '>', 'deadline')->count(),
                'delivered_late' => $deliveredLate,
                'delivered_on_time' => $deliveredOnTime,
                // On-time delivery rate across all delivered mailings (null until any are delivered).
                'on_time_rate' => $totalDelivered > 0 ? (int) round($deliveredOnTime / $totalDelivered * 100) : null,
            ],
            'recent' => $base()
                ->with('proposalSubmission:id,project_name,proposal_number')
                ->latest()->limit(5)->get()->map(fn (ProposalMailing $m) => [
                    'ulid' => $m->ulid,
                    'ups_tracking_number' => $m->ups_tracking_number,
                    'recipient_name' => $m->recipient_name,
                    'status_label' => $m->status->label(),
                    'status_color' => $m->status->color(),
                    'risk_label' => $m->risk()->label(),
                    'risk_color' => $m->risk()->color(),
                    'deadline' => optional($m->deadline)->toDateString(),
                ]),
        ]);
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ProposalMailing::class);
        $orgId = $request->user()->organization_id;

        $mailings = ProposalMailing::query()
            ->forOrganization($orgId)
            ->with('proposalSubmission:id,project_name,proposal_number')
            ->when($request->string('status')->toString(), fn ($q, $s) => $s === 'active'
                ? $q->active()                       // "En route" — everything not delivered/returned
                : $q->where('status', $s))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (ProposalMailing $m) => $this->present($m));

        return Inertia::render('Shipments/Mailings/Index', [
            'mailings' => $mailings,
            'filters' => ['status' => $request->string('status')->toString() ?: null],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', ProposalMailing::class);
        $orgId = $request->user()->organization_id;

        $prefill = null;
        if ($id = $request->integer('proposal')) {
            $proposal = ProposalSubmission::query()
                ->where('organization_id', $orgId)->with('agency')->find($id);
            if ($proposal) {
                $prefill = [
                    'proposal_submission_id' => $proposal->id,
                    'deadline' => optional($proposal->due_date)->toDateString(),
                    'recipient_name' => $proposal->agency?->name,
                    'recipient_address' => $proposal->agency ? $this->agencyAddress($proposal->agency) : null,
                ];
            }
        }

        $linkable = ProposalSubmission::query()
            ->where('organization_id', $orgId)
            ->whereDoesntHave('mailing')
            ->orderByDesc('due_date')
            ->limit(100)
            ->get(['id', 'project_name', 'proposal_number', 'due_date'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'label' => trim(($p->proposal_number ? $p->proposal_number.' — ' : '').$p->project_name),
                'due_date' => optional($p->due_date)->toDateString(),
            ]);

        return Inertia::render('Shipments/Mailings/Create', [
            'prefill' => $prefill,
            'linkableProposals' => $linkable,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ProposalMailing::class);
        $orgId = $request->user()->organization_id;

        $supportedCarriers = collect(\App\Enums\Carrier::cases())
            ->filter(fn ($c) => $c->supported())->map(fn ($c) => $c->value)->all();

        $data = $request->validate([
            'ups_tracking_number' => ['required', 'string', 'max:64'],
            'carrier' => ['nullable', Rule::in($supportedCarriers)],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'recipient_address' => ['nullable', 'string', 'max:1000'],
            'deadline' => ['nullable', 'date'],
            'proposal_submission_id' => [
                'nullable',
                Rule::exists('proposal_submissions', 'id')->where('organization_id', $orgId),
            ],
        ]);

        $mailing = new ProposalMailing($data);
        $mailing->organization_id = $orgId;
        $mailing->created_by = $request->user()->id;
        $mailing->carrier = $data['carrier'] ?? 'ups';
        $mailing->save();

        try {
            // notify:false — don't alert on the initial population the user just created.
            $this->tracking->refresh($mailing, notify: false);
        } catch (\Throwable $e) {
            return redirect()->route('shipments.mailings.show', $mailing->ulid)
                ->with('warning', 'Mailing created, but the first UPS lookup failed. It will retry on the next poll.');
        }

        return redirect()->route('shipments.mailings.show', $mailing->ulid)
            ->with('success', 'Mailing created and tracked.');
    }

    public function bulkCreate(Request $request): Response
    {
        $this->authorize('create', ProposalMailing::class);

        return Inertia::render('Shipments/Mailings/Bulk');
    }

    /**
     * Load many existing shipments at once by pasting their tracking numbers.
     * Each is created + fetched from the carrier immediately so it lands in the
     * list with its real status (delivered = past, in transit = en route).
     */
    public function bulkStore(Request $request): RedirectResponse
    {
        $this->authorize('create', ProposalMailing::class);
        $orgId = $request->user()->organization_id;

        $supported = collect(\App\Enums\Carrier::cases())
            ->filter(fn ($c) => $c->supported())->map(fn ($c) => $c->value)->all();

        $data = $request->validate([
            'tracking_numbers' => ['required', 'string', 'max:20000'],
            'carrier' => ['nullable', Rule::in($supported)],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'deadline' => ['nullable', 'date'],
        ]);

        $carrier = $data['carrier'] ?? 'ups';

        // Split on whitespace/commas, dedupe, cap per submit so the synchronous
        // carrier lookups stay within the request timeout.
        $numbers = collect(preg_split('/[\s,]+/', $data['tracking_numbers']))
            ->map(fn ($n) => trim($n))->filter()->unique()->take(50);

        $added = 0;
        $existed = 0;
        $failed = 0;

        foreach ($numbers as $tn) {
            if (ProposalMailing::where('organization_id', $orgId)->where('ups_tracking_number', $tn)->exists()) {
                $existed++;

                continue;
            }

            $mailing = new ProposalMailing([
                'ups_tracking_number' => $tn,
                'recipient_name' => $data['recipient_name'] ?? null,
                'deadline' => $data['deadline'] ?? null,
            ]);
            $mailing->organization_id = $orgId;
            $mailing->created_by = $request->user()->id;
            $mailing->carrier = $carrier;
            $mailing->save();

            try {
                $this->tracking->refresh($mailing, notify: false);
                $added++;
            } catch (\Throwable $e) {
                // Keep the row — the 30-minute poller will retry the lookup.
                $failed++;
            }
        }

        $parts = ["Added {$added} shipment(s)"];
        if ($existed) {
            $parts[] = "{$existed} already tracked";
        }
        if ($failed) {
            $parts[] = "{$failed} couldn't be fetched yet (will retry on the next poll)";
        }

        return redirect()->route('shipments.mailings.index')
            ->with($failed && ! $added ? 'warning' : 'success', implode(' · ', $parts).'.');
    }

    public function show(Request $request, string $ulid): Response
    {
        $mailing = ProposalMailing::query()
            ->forOrganization($request->user()->organization_id)
            ->with(['trackingEvents', 'proposalSubmission:id,project_name,proposal_number', 'createdBy:id,name'])
            ->where('ulid', $ulid)
            ->firstOrFail();

        $this->authorize('view', $mailing);

        return Inertia::render('Shipments/Mailings/Show', [
            'mailing' => $this->present($mailing, withTimeline: true),
        ]);
    }

    public function refresh(Request $request, string $ulid): RedirectResponse
    {
        $mailing = ProposalMailing::query()
            ->forOrganization($request->user()->organization_id)
            ->where('ulid', $ulid)
            ->firstOrFail();

        $this->authorize('update', $mailing);

        try {
            $this->tracking->refresh($mailing);
        } catch (\Throwable $e) {
            return back()->with('error', 'UPS lookup failed: '.$e->getMessage());
        }

        return back()->with('success', 'Tracking refreshed.');
    }

    private function agencyAddress($agency): string
    {
        return collect([
            $agency->address_line1,
            trim(collect([$agency->city, $agency->state])->filter()->implode(', ')),
            $agency->zip,
        ])->filter()->implode("\n");
    }

    private function present(ProposalMailing $m, bool $withTimeline = false): array
    {
        $risk = $m->risk();

        $data = [
            'id' => $m->id,
            'ulid' => $m->ulid,
            'ups_tracking_number' => $m->ups_tracking_number,
            'carrier' => $m->carrier,
            'carrier_label' => \App\Enums\Carrier::tryFrom($m->carrier)?->label() ?? strtoupper((string) $m->carrier),
            'tracking_url' => \App\Enums\Carrier::tryFrom($m->carrier)?->trackingUrl($m->ups_tracking_number),
            'recipient_name' => $m->recipient_name,
            'recipient_address' => $m->recipient_address,
            'deadline' => optional($m->deadline)->toDateString(),
            'status' => $m->status->value,
            'status_label' => $m->status->label(),
            'status_color' => $m->status->color(),
            'risk' => $risk->value,
            'risk_label' => $risk->label(),
            'risk_color' => $risk->color(),
            'scheduled_delivery' => optional($m->scheduled_delivery)->toDateString(),
            'delivered_at' => optional($m->delivered_at)->toIso8601String(),
            'received_by' => $m->received_by,
            'on_time' => $m->on_time,
            'proof_url' => $m->proof_url,
            'proposal' => $m->proposalSubmission ? [
                'id' => $m->proposalSubmission->id,
                'project_name' => $m->proposalSubmission->project_name,
                'proposal_number' => $m->proposalSubmission->proposal_number,
            ] : null,
            'created_at' => optional($m->created_at)->toIso8601String(),
        ];

        if ($withTimeline) {
            $data['created_by'] = $m->createdBy?->name;
            $data['events'] = $m->trackingEvents->map(fn ($e) => [
                'id' => $e->id,
                'code' => $e->code,
                'description' => $e->description,
                'location' => $e->location,
                'occurred_at' => $e->occurred_at->toIso8601String(),
            ]);
        }

        return $data;
    }
}
