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
    public function __construct(
        private readonly MailingTrackingService $tracking,
        private readonly \App\Services\Mailings\ShipmentProposalMatcher $matcher,
    ) {}

    public function dashboard(Request $request): Response
    {
        $orgId = $request->user()->organization_id;

        // Resilient to a pending migration: the scope column may not exist yet.
        $hasScope = \Illuminate\Support\Facades\Schema::hasColumn('proposal_mailings', 'scope');

        // Optional domestic/international filter for the whole dashboard.
        $scope = $hasScope && in_array($request->string('scope')->toString(), ['domestic', 'international'], true)
            ? $request->string('scope')->toString() : null;

        $base = fn () => ProposalMailing::query()->forOrganization($orgId)
            ->when($scope, fn ($q) => $q->where('scope', $scope));

        $deliveredOnTime = (clone $base())->where('status', MailingStatus::Delivered->value)->where('on_time', true)->count();
        $deliveredLate = (clone $base())->where('status', MailingStatus::Delivered->value)->where('on_time', false)->count();
        $totalDelivered = $deliveredOnTime + $deliveredLate;

        $scopeCounts = $hasScope
            ? ProposalMailing::query()->forOrganization($orgId)->selectRaw('scope, count(*) c')->groupBy('scope')->pluck('c', 'scope')
            : collect();

        // Shipments that need a human to look at them: delivery exceptions,
        // returns, running late (ETA past the deadline / overdue), or delivered
        // late. Most urgent (actionable) first.
        $today = now()->toDateString();
        $issues = $base()
            ->where(function ($q) use ($today) {
                $q->whereIn('status', [MailingStatus::Exception->value, MailingStatus::Returned->value])
                    ->orWhere(fn ($x) => $x->where('status', MailingStatus::Delivered->value)->where('on_time', false))
                    ->orWhere(fn ($x) => $x->whereNotIn('status', [MailingStatus::Delivered->value, MailingStatus::Returned->value])
                        ->whereNotNull('deadline')
                        ->where(function ($y) use ($today) {
                            $y->whereColumn('scheduled_delivery', '>', 'deadline')
                                ->orWhere(fn ($z) => $z->whereNull('scheduled_delivery')->whereDate('deadline', '<', $today));
                        }));
            })
            ->with('latestEvent')
            ->orderByRaw("CASE WHEN status = 'exception' THEN 1 WHEN status NOT IN ('delivered','returned') THEN 2 WHEN status = 'returned' THEN 3 ELSE 4 END")
            ->latest()
            ->limit(15)
            ->get()
            ->map(function (ProposalMailing $m) {
                [$label, $color] = match (true) {
                    $m->status === MailingStatus::Exception => ['Delivery exception', 'red'],
                    $m->status === MailingStatus::Returned => ['Returned to sender', 'amber'],
                    $m->status === MailingStatus::Delivered => ['Delivered late', 'red'],
                    default => ['Running late', 'amber'],
                };

                return [
                    'ulid' => $m->ulid,
                    'ups_tracking_number' => $m->ups_tracking_number,
                    'recipient_name' => $m->recipient_name,
                    'issue_label' => $label,
                    'issue_color' => $color,
                    'status_label' => $m->status->label(),
                    'current_location' => $m->latestEvent?->location,
                    'deadline' => optional($m->deadline)->toDateString(),
                ];
            });

        return Inertia::render('Shipments/Index', [
            'scope' => $scope,
            'scopeCounts' => [
                'all' => $hasScope ? (int) $scopeCounts->sum() : ProposalMailing::query()->forOrganization($orgId)->count(),
                'domestic' => (int) ($scopeCounts['domestic'] ?? 0),
                'international' => (int) ($scopeCounts['international'] ?? 0),
            ],
            'stats' => [
                'active' => (clone $base())->active()->count(),
                'at_risk' => (clone $base())->active()
                    ->whereNotNull('deadline')->whereNotNull('scheduled_delivery')
                    ->whereColumn('scheduled_delivery', '>', 'deadline')->count(),
                'delivered_late' => $deliveredLate,
                'delivered_on_time' => $deliveredOnTime,
                'on_time_rate' => $totalDelivered > 0 ? (int) round($deliveredOnTime / $totalDelivered * 100) : null,
            ],
            'recent' => $base()
                ->with(['proposalSubmission:id,project_name,proposal_number', 'latestEvent'])
                ->latest()->limit(6)->get()->map(fn (ProposalMailing $m) => [
                    'ulid' => $m->ulid,
                    'ups_tracking_number' => $m->ups_tracking_number,
                    'recipient_name' => $m->recipient_name,
                    'scope_label' => $hasScope ? ($m->scope?->label() ?? 'Domestic') : 'Domestic',
                    'scope_color' => $hasScope ? ($m->scope?->color() ?? 'blue') : 'blue',
                    'status_label' => $m->status->label(),
                    'status_color' => $m->status->color(),
                    'risk_label' => $m->risk()->label(),
                    'risk_color' => $m->risk()->color(),
                    'current_location' => $m->latestEvent?->location,
                    'deadline' => optional($m->deadline)->toDateString(),
                ]),
            'issues' => $issues,
        ]);
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ProposalMailing::class);
        $orgId = $request->user()->organization_id;

        $sort = $request->string('sort')->toString() ?: 'recent';
        $dir = strtolower($request->string('dir')->toString()) === 'asc' ? 'asc' : 'desc';

        // Dashboard-tile drill-downs. Each matches the tile's count exactly.
        $filter = $request->string('filter')->toString();
        $scope = in_array($request->string('scope')->toString(), ['domestic', 'international'], true)
            ? $request->string('scope')->toString() : null;

        $query = ProposalMailing::query()
            ->forOrganization($orgId)
            ->with(['proposalSubmission:id,project_name,proposal_number', 'latestEvent'])
            ->when($scope, fn ($q) => $q->where('scope', $scope))
            ->when($request->string('status')->toString(), fn ($q, $s) => $s === 'active'
                ? $q->active()                       // "En route" — everything not delivered/returned
                : $q->where('status', $s))
            ->when($filter === 'at_risk', fn ($q) => $q->active()
                ->whereNotNull('deadline')->whereNotNull('scheduled_delivery')
                ->whereColumn('scheduled_delivery', '>', 'deadline'))
            ->when($filter === 'delivered_late', fn ($q) => $q
                ->where('status', MailingStatus::Delivered->value)->where('on_time', false))
            ->when($filter === 'delivered_on_time', fn ($q) => $q
                ->where('status', MailingStatus::Delivered->value)->where('on_time', true));

        $this->applySort($query, $sort, $dir);

        $mailings = $query
            ->paginate(20)
            ->withQueryString()
            ->through(fn (ProposalMailing $m) => $this->present($m));

        return Inertia::render('Shipments/Mailings/Index', [
            'mailings' => $mailings,
            'filters' => [
                'status' => $request->string('status')->toString() ?: null,
                'filter' => $filter ?: null,
                'scope' => $scope,
                'sort' => $sort,
                'dir' => $dir,
            ],
        ]);
    }

    /**
     * Apply a whitelisted sort. $dir is constrained to asc|desc and $sort to the
     * cases below, so the raw fragments are safe. NULLs (e.g. not-yet-delivered)
     * always sort last; a stable id tiebreaker keeps pagination consistent.
     */
    private function applySort(\Illuminate\Database\Eloquent\Builder $query, string $sort, string $dir): void
    {
        $events = '(SELECT MIN(occurred_at) FROM mailing_tracking_events WHERE mailing_tracking_events.proposal_mailing_id = proposal_mailings.id)';

        match ($sort) {
            // Logical progression rather than alphabetical.
            'status' => $query->orderByRaw("FIELD(status, 'label_created','in_transit','out_for_delivery','exception','delivered','returned') $dir"),
            'delivered' => $query->orderByRaw("delivered_at IS NULL, delivered_at $dir"),
            'scheduled' => $query->orderByRaw("scheduled_delivery IS NULL, scheduled_delivery $dir"),
            'deadline' => $query->orderByRaw("deadline IS NULL, deadline $dir"),
            // Shipping date = when the label was created (first UPS scan), falling
            // back to when the shipment was added.
            'label_created' => $query->orderByRaw("COALESCE($events, proposal_mailings.created_at) $dir"),
            default => $query->orderBy('created_at', $dir),
        };

        $query->orderBy('proposal_mailings.id', 'desc');
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
            'scope' => ['nullable', Rule::enum(\App\Enums\ShipmentScope::class)],
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

        // Auto-link to a matching proposal when the user didn't pick one.
        if (! $mailing->proposal_submission_id) {
            $this->matcher->matchOne($mailing);
        }

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
            'scope' => ['nullable', Rule::enum(\App\Enums\ShipmentScope::class)],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'deadline' => ['nullable', 'date'],
        ]);

        $carrier = $data['carrier'] ?? 'ups';
        $scope = $data['scope'] ?? 'domestic';

        // Split on whitespace/commas, drop separators/labels, dedupe.
        $numbers = collect(preg_split('/[\s,]+/', $data['tracking_numbers']))
            ->map(fn ($n) => trim($n))->filter()->unique()->values();

        $capped = $numbers->count() > 100;

        $rows = $numbers->take(100)->map(fn ($tn) => [
            'tracking_number' => $tn,
            'carrier' => $carrier,
            'scope' => $scope,
            'recipient_name' => $data['recipient_name'] ?? null,
            'deadline' => $data['deadline'] ?? null,
        ]);

        $result = $this->persistAndTrack($orgId, $request->user()->id, $rows);
        $linked = $this->matcher->matchOrganization($orgId);

        return $this->importRedirect($result, $capped, null, $linked);
    }

    public function importCreate(Request $request): Response
    {
        $this->authorize('create', ProposalMailing::class);

        return Inertia::render('Shipments/Mailings/Import');
    }

    /**
     * Dump documents (CSV, label PDFs, photos of labels) and/or paste tracking
     * numbers; we read the shipping info out of each and create the shipments.
     */
    public function importStore(Request $request, \App\Services\Mailings\ShipmentImportService $importer): RedirectResponse
    {
        $this->authorize('create', ProposalMailing::class);
        $orgId = $request->user()->organization_id;

        $supported = collect(\App\Enums\Carrier::cases())
            ->filter(fn ($c) => $c->supported())->map(fn ($c) => $c->value)->all();

        $incoming = $request->file('files', []);
        $incoming = is_array($incoming) ? $incoming : [$incoming];

        // Diagnostic: what actually arrived (file names/types + paste length).
        \Illuminate\Support\Facades\Log::info('shipment import received', [
            'has_file' => $request->hasFile('files'),
            'count' => count($incoming),
            'names' => array_map(fn ($f) => $f?->getClientOriginalName(), $incoming),
            'mimes' => array_map(fn ($f) => $f?->getMimeType(), $incoming),
            'exts' => array_map(fn ($f) => $f?->getClientOriginalExtension(), $incoming),
            'pasted_len' => strlen((string) $request->input('pasted_text')),
        ]);

        // Validate by file EXTENSION (not detected MIME): a CSV is routinely
        // sniffed as text/plain or application/vnd.ms-excel, which a mimes: rule
        // would wrongly reject.
        $request->validate([
            'files' => ['nullable', 'array', 'max:20'],
            'files.*' => ['file', 'max:51200', 'extensions:pdf,png,jpg,jpeg,webp,gif,csv,txt,md,doc,docx'],
            'pasted_text' => ['nullable', 'string', 'max:20000'],
            'carrier' => ['nullable', Rule::in($supported)],
            'scope' => ['nullable', Rule::enum(\App\Enums\ShipmentScope::class)],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'deadline' => ['nullable', 'date'],
        ]);

        $files = $incoming;
        $pasted = $request->input('pasted_text');

        if ($files === [] && (! $pasted || trim((string) $pasted) === '')) {
            return back()->with('warning', 'Add at least one file or paste some tracking numbers.');
        }

        $defaults = [
            'carrier' => $request->input('carrier', 'ups'),
            'scope' => $request->input('scope', 'domestic'),
            'recipient_name' => $request->input('recipient_name'),
            'deadline' => $request->input('deadline'),
        ];

        $built = $importer->build($files, $pasted, $defaults);

        \Illuminate\Support\Facades\Log::info('shipment import parsed', [
            'candidates' => $built['candidates']->count(),
            'sources' => $built['sources'],
        ]);

        $capped = $built['candidates']->count() > 100;

        $result = $this->persistAndTrack($orgId, $request->user()->id, $built['candidates']->take(100));
        $linked = $this->matcher->matchOrganization($orgId);

        return $this->importRedirect($result, $capped, 'No tracking numbers could be read from what you provided. Try a CSV, a clearer label, or paste the codes.', $linked);
    }

    /**
     * Create new shipment rows from candidate data (deduped, org-scoped), then
     * fetch live status inline within a wall-clock budget so a big batch can't
     * run past the request timeout. Anything not reached stays pending and is
     * filled in by the poller / manual refresh.
     *
     * @param  iterable<array<string,mixed>>  $rows
     * @return array{added:int, existed:int, pending:int}
     */
    private function persistAndTrack(int $orgId, int $userId, iterable $rows): array
    {
        $created = [];
        $existed = 0;
        $seen = [];

        foreach ($rows as $row) {
            $tn = trim((string) ($row['tracking_number'] ?? ''));
            if ($tn === '') {
                continue;
            }
            $key = strtoupper($tn);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            if (ProposalMailing::where('organization_id', $orgId)->where('ups_tracking_number', $tn)->exists()) {
                $existed++;

                continue;
            }

            $mailing = new ProposalMailing([
                'ups_tracking_number' => $tn,
                'recipient_name' => $row['recipient_name'] ?? null,
                'recipient_address' => $row['recipient_address'] ?? null,
                'deadline' => $row['deadline'] ?? null,
                'scope' => $row['scope'] ?? 'domestic',
            ]);
            $mailing->organization_id = $orgId;
            $mailing->created_by = $userId;
            $mailing->carrier = $row['carrier'] ?? 'ups';
            $mailing->save();

            $created[] = $mailing;
        }

        $budgetUntil = microtime(true) + 20.0;
        $pending = 0;
        foreach ($created as $mailing) {
            if (microtime(true) >= $budgetUntil) {
                $pending++;

                continue;
            }

            try {
                $this->tracking->refresh($mailing, notify: false);
            } catch (\Throwable $e) {
                $pending++;
            }
        }

        return ['added' => count($created), 'existed' => $existed, 'pending' => $pending];
    }

    /**
     * @param  array{added:int, existed:int, pending:int}  $r
     */
    private function importRedirect(array $r, bool $capped = false, ?string $emptyMessage = null, int $linked = 0): RedirectResponse
    {
        if ($r['added'] === 0) {
            $msg = $r['existed']
                ? "All {$r['existed']} tracking number(s) are already being tracked."
                : ($emptyMessage ?? 'No valid tracking numbers were found.');

            return redirect()->route('shipments.mailings.index')->with('warning', $msg);
        }

        $parts = ["Added {$r['added']} shipment(s)"];
        if ($r['existed']) {
            $parts[] = "{$r['existed']} already tracked";
        }
        if ($linked) {
            $parts[] = "auto-linked {$linked} to a proposal";
        }
        if ($r['pending']) {
            $parts[] = "{$r['pending']} still loading (will update automatically)";
        }
        if ($capped) {
            $parts[] = 'capped at the first 100 — submit the rest separately';
        }

        return redirect()->route('shipments.mailings.index')
            ->with('success', implode(' · ', $parts).'.');
    }

    public function show(Request $request, string $ulid): Response
    {
        $mailing = ProposalMailing::query()
            ->forOrganization($request->user()->organization_id)
            ->with(['trackingEvents', 'documents', 'proposalSubmission:id,project_name,proposal_number', 'createdBy:id,name'])
            ->where('ulid', $ulid)
            ->firstOrFail();

        $this->authorize('view', $mailing);

        // Proposals available to link by hand: any not already tied to a shipment,
        // plus whichever one this shipment is currently linked to.
        $linkable = ProposalSubmission::query()
            ->where('organization_id', $request->user()->organization_id)
            ->where(function ($q) use ($mailing) {
                $q->whereDoesntHave('mailing');
                if ($mailing->proposal_submission_id) {
                    $q->orWhere('id', $mailing->proposal_submission_id);
                }
            })
            ->orderByDesc('due_date')
            ->limit(200)
            ->get(['id', 'project_name', 'proposal_number', 'due_date'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'label' => trim(($p->proposal_number ? $p->proposal_number.' — ' : '').$p->project_name),
                'due_date' => optional($p->due_date)->toDateString(),
            ]);

        return Inertia::render('Shipments/Mailings/Show', [
            'mailing' => $this->present($mailing, withTimeline: true),
            'linkableProposals' => $linkable,
        ]);
    }

    public function update(Request $request, string $ulid): RedirectResponse
    {
        $mailing = ProposalMailing::query()
            ->forOrganization($request->user()->organization_id)
            ->where('ulid', $ulid)
            ->firstOrFail();

        $this->authorize('update', $mailing);

        $orgId = $request->user()->organization_id;
        $supportedCarriers = collect(\App\Enums\Carrier::cases())
            ->filter(fn ($c) => $c->supported())->map(fn ($c) => $c->value)->all();

        $data = $request->validate([
            'ups_tracking_number' => [
                'required', 'string', 'max:64',
                Rule::unique('proposal_mailings', 'ups_tracking_number')
                    ->where('organization_id', $orgId)->ignore($mailing->id),
            ],
            'carrier' => ['nullable', Rule::in($supportedCarriers)],
            'proposal_submission_id' => [
                'nullable',
                Rule::exists('proposal_submissions', 'id')->where('organization_id', $orgId),
            ],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'recipient_address' => ['nullable', 'string', 'max:1000'],
            'deadline' => ['nullable', 'date'],
            'scope' => ['required', Rule::enum(\App\Enums\ShipmentScope::class)],
            'status' => ['required', Rule::enum(MailingStatus::class)],
            'scheduled_delivery' => ['nullable', 'date'],
            'delivered_at' => ['nullable', 'date'],
            'received_by' => ['nullable', 'string', 'max:255'],
            'auto_track' => ['sometimes', 'boolean'],
        ]);

        $mailing->ups_tracking_number = $data['ups_tracking_number'];
        $mailing->carrier = $data['carrier'] ?? $mailing->carrier;
        $mailing->proposal_submission_id = $data['proposal_submission_id'] ?? null;
        $mailing->recipient_name = $data['recipient_name'] ?? null;
        $mailing->recipient_address = $data['recipient_address'] ?? null;
        $mailing->deadline = $data['deadline'] ?? null;
        $mailing->scope = $data['scope'];
        $mailing->status = $data['status'];
        $mailing->scheduled_delivery = $data['scheduled_delivery'] ?? null;
        $mailing->received_by = $data['received_by'] ?? null;

        // Keep delivered details + the cached on-time result consistent with the
        // status the user picked.
        if ($data['status'] === MailingStatus::Delivered->value) {
            $mailing->delivered_at = $data['delivered_at'] ?? $mailing->delivered_at ?? now();
            $mailing->on_time = ($mailing->deadline && $mailing->delivered_at)
                ? $mailing->delivered_at->copy()->startOfDay()->lte($mailing->deadline->copy()->startOfDay())
                : null;
        } else {
            $mailing->delivered_at = $data['delivered_at'] ?? null;
            $mailing->on_time = null;
        }

        // Pause/resume automatic UPS sync so a manual override isn't reverted by
        // the next poll. Resilient to the column not existing yet.
        if (\Illuminate\Support\Facades\Schema::hasColumn('proposal_mailings', 'auto_track')) {
            $mailing->auto_track = $request->boolean('auto_track');
        }

        $mailing->save();

        return back()->with('success', 'Shipment updated.');
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

    /**
     * Pull live status + location from UPS for every active shipment at once
     * (the "Update all" button). Reads over HTTP from the carrier, bounded by a
     * wall-clock budget; manually-overridden shipments (auto_track off) are left
     * untouched. Whatever isn't reached is picked up by the 30-minute poll.
     */
    public function refreshAll(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', ProposalMailing::class);
        $orgId = $request->user()->organization_id;

        $query = ProposalMailing::query()->forOrganization($orgId)->active();
        if (\Illuminate\Support\Facades\Schema::hasColumn('proposal_mailings', 'auto_track')) {
            $query->where('auto_track', true);
        }
        $mailings = $query->orderBy('id')->limit(150)->get();

        if ($mailings->isEmpty()) {
            return back()->with('warning', 'No active shipments to update.');
        }

        $budgetUntil = microtime(true) + 22.0;
        $updated = 0;
        $skipped = 0;
        foreach ($mailings as $m) {
            if (microtime(true) >= $budgetUntil) {
                $skipped++;

                continue;
            }
            try {
                $this->tracking->refresh($m, notify: false);
                $updated++;
            } catch (\Throwable $e) {
                $skipped++;
            }
        }

        $msg = "Updated {$updated} shipment(s) from UPS".($skipped ? " · {$skipped} left for the next run" : '').'.';

        return back()->with($updated ? 'success' : 'warning', $updated ? $msg : 'UPS had no new data right now.');
    }

    /**
     * Read proposals from the database and auto-link any unlinked shipment to the
     * proposal it clearly belongs to (the "Match to proposals" button).
     */
    public function matchProposals(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', ProposalMailing::class);

        $linked = $this->matcher->matchOrganization($request->user()->organization_id);

        return back()->with($linked ? 'success' : 'warning',
            $linked ? "Linked {$linked} shipment(s) to a matching proposal." : 'No new proposal matches found.');
    }

    private function latestEvent(ProposalMailing $m): ?\App\Models\MailingTrackingEvent
    {
        if ($m->relationLoaded('latestEvent')) {
            return $m->latestEvent;
        }
        if ($m->relationLoaded('trackingEvents')) {
            return $m->trackingEvents->first();
        }

        return null;
    }

    private function currentLocation(ProposalMailing $m): ?string
    {
        return $this->latestEvent($m)?->location;
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
            'scope' => $m->scope?->value ?? 'domestic',
            'scope_label' => $m->scope?->label() ?? 'Domestic',
            'scope_color' => $m->scope?->color() ?? 'blue',
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
            'delivered_on' => optional($m->delivered_at)->toDateString(),
            'received_by' => $m->received_by,
            'on_time' => $m->on_time,
            'current_location' => $this->currentLocation($m),
            'last_update' => optional($this->latestEvent($m)?->occurred_at)->toIso8601String(),
            'auto_track' => \Illuminate\Support\Facades\Schema::hasColumn('proposal_mailings', 'auto_track')
                ? (bool) $m->auto_track : true,
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
            $data['documents'] = $m->documents->map(fn ($d) => [
                'id' => $d->id,
                'name' => $d->display_name,
                'type' => $d->document_type,
                'size' => $d->size_formatted,
                'is_image' => str_starts_with((string) $d->mime_type, 'image/'),
                'download_url' => "/shipments/mailings/{$m->ulid}/documents/{$d->id}/download",
                'preview_url' => "/shipments/mailings/{$m->ulid}/documents/{$d->id}/preview",
                'created_at' => optional($d->created_at)->toIso8601String(),
            ]);
        }

        return $data;
    }
}
