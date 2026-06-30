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
        private readonly \App\Services\Mailings\CarrierRegistry $carrierRegistry,
        private readonly \App\Services\Mailings\MailingIngestService $ingest,
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
                ->with(['proposalSubmission:id,project_name,proposal_number', 'latestEvent', 'labelCreatedEvent'])
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
                    'label_created_at' => optional($m->labelCreatedEvent?->occurred_at)->toDateString(),
                ]),
            'issues' => $issues,
        ]);
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ProposalMailing::class);
        $orgId = $request->user()->organization_id;

        $mailings = $this->listQuery($request, $orgId)
            ->paginate(20)
            ->withQueryString()
            ->through(fn (ProposalMailing $m) => $this->present($m));

        $scope = in_array($request->string('scope')->toString(), ['domestic', 'international'], true)
            ? $request->string('scope')->toString() : null;

        return Inertia::render('Shipments/Mailings/Index', [
            'mailings' => $mailings,
            'filters' => [
                'status' => $request->string('status')->toString() ?: null,
                'filter' => $request->string('filter')->toString() ?: null,
                'scope' => $scope,
                'carrier' => $request->string('carrier')->toString() ?: null,
                'q' => trim($request->string('q')->toString()) ?: null,
                'sort' => $request->string('sort')->toString() ?: 'recent',
                'dir' => strtolower($request->string('dir')->toString()) === 'asc' ? 'asc' : 'desc',
            ],
            'carrierOptions' => $this->usedCarrierOptions($orgId),
            // Full carrier set (known + custom) for the bulk "reassign" action.
            'reassignCarrierOptions' => $this->carrierOptions($orgId),
        ]);
    }

    /**
     * The shipments list query with every active filter applied — scope, carrier,
     * the free-text search (tracking #, recipient, or linked proposal name/number),
     * the status pill, and the dashboard drill-down filters — plus the chosen sort.
     * Shared by the list view and the CSV export so both honour the same filters.
     */
    private function listQuery(Request $request, int $orgId): \Illuminate\Database\Eloquent\Builder
    {
        $sort = $request->string('sort')->toString() ?: 'recent';
        $dir = strtolower($request->string('dir')->toString()) === 'asc' ? 'asc' : 'desc';

        // Dashboard-tile drill-downs. Each matches the tile's count exactly.
        $filter = $request->string('filter')->toString();
        $scope = in_array($request->string('scope')->toString(), ['domestic', 'international'], true)
            ? $request->string('scope')->toString() : null;
        $carrier = $request->string('carrier')->toString() ?: null;
        $search = trim($request->string('q')->toString());

        $query = ProposalMailing::query()
            ->forOrganization($orgId)
            ->with(['proposalSubmission:id,project_name,proposal_number', 'latestEvent', 'labelCreatedEvent'])
            ->when($scope, fn ($q) => $q->where('scope', $scope))
            ->when($carrier, fn ($q) => $q->where('carrier', $carrier))
            ->when($search !== '', fn ($q) => $q->where(function ($w) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $w->where('ups_tracking_number', 'like', $like)
                    ->orWhere('recipient_name', 'like', $like)
                    ->orWhereHas('proposalSubmission', fn ($p) => $p
                        ->where('project_name', 'like', $like)
                        ->orWhere('proposal_number', 'like', $like));
            }))
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

        return $query;
    }

    /**
     * Download the current (filtered + sorted) shipments list as a CSV, so "what
     * you see is what you export". Honours the same query params as the list.
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorize('viewAny', ProposalMailing::class);
        $orgId = $request->user()->organization_id;

        $mailings = $this->listQuery($request, $orgId)->limit(5000)->get();
        $filename = 'shipments-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($mailings) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Tracking Number', 'Carrier', 'Reference Type', 'Recipient', 'Category',
                'Status', 'On-time', 'Current Location', 'Proposal', 'Label Created',
                'Deadline', 'Estimated Delivery', 'Delivered On', 'Received By',
            ]);

            foreach ($mailings as $m) {
                fputcsv($out, [
                    (string) $m->ups_tracking_number,
                    \App\Enums\Carrier::tryFrom($m->carrier)?->label() ?: ($m->carrier ?: ''),
                    $m->reference_type
                        ? (\App\Enums\JbHuntReferenceType::tryFrom($m->reference_type)?->label() ?? $m->reference_type)
                        : '',
                    (string) $m->recipient_name,
                    $m->scope?->label() ?? 'Domestic',
                    $m->status->label(),
                    $m->risk()->label(),
                    (string) $this->currentLocation($m),
                    (string) ($m->proposalSubmission?->proposal_number ?? $m->proposalSubmission?->project_name),
                    (string) optional($m->labelCreatedEvent?->occurred_at)->toDateString(),
                    (string) optional($m->deadline)->toDateString(),
                    (string) optional($m->scheduled_delivery)->toDateString(),
                    (string) optional($m->delivered_at)->toDateString(),
                    (string) $m->received_by,
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Carriers actually present on this org's shipments, for the list's "by
     * carrier" filter — so every option yields results. Built-in carriers show
     * their label; custom carrier names are already human-readable.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function usedCarrierOptions(int $orgId): array
    {
        return ProposalMailing::query()
            ->forOrganization($orgId)
            ->whereNotNull('carrier')
            ->where('carrier', '!=', '')
            ->distinct()
            ->orderBy('carrier')
            ->pluck('carrier')
            ->map(fn ($c) => [
                'value' => (string) $c,
                'label' => \App\Enums\Carrier::tryFrom((string) $c)?->label() ?? (string) $c,
            ])
            ->values()
            ->all();
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
            'carrierOptions' => $this->carrierOptions($orgId),
            'referenceTypeOptions' => \App\Enums\JbHuntReferenceType::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ProposalMailing::class);
        $orgId = $request->user()->organization_id;

        $data = $request->validate([
            'ups_tracking_number' => ['required', 'string', 'max:64'],
            // A free-text carrier so custom/freight carriers (e.g. J.B. Hunt) can be
            // added; known carriers are normalised back to their enum value below.
            'carrier' => ['nullable', 'string', 'max:50'],
            // The kind of number entered, used by carriers with reference types (J.B. Hunt).
            'reference_type' => ['nullable', Rule::enum(\App\Enums\JbHuntReferenceType::class)],
            'scope' => ['nullable', Rule::enum(\App\Enums\ShipmentScope::class)],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'recipient_address' => ['nullable', 'string', 'max:1000'],
            'deadline' => ['nullable', 'date'],
            'proposal_submission_id' => [
                'nullable',
                Rule::exists('proposal_submissions', 'id')->where('organization_id', $orgId),
            ],
        ]);

        $carrier = $this->normalizeCarrier($data['carrier'] ?? null);

        $mailing = new ProposalMailing($data);
        $mailing->organization_id = $orgId;
        $mailing->created_by = $request->user()->id;
        $mailing->carrier = $carrier;
        $mailing->reference_type = $this->normalizeReferenceType($carrier, $data['reference_type'] ?? null);
        // Carriers without a live integration can't be auto-synced — track manually.
        if (! $this->carrierIsLive($carrier) && \Illuminate\Support\Facades\Schema::hasColumn('proposal_mailings', 'auto_track')) {
            $mailing->auto_track = false;
        }
        $mailing->save();

        // Auto-link to a matching proposal when the user didn't pick one.
        if (! $mailing->proposal_submission_id) {
            $this->matcher->matchOne($mailing);
        }

        if (! $this->carrierIsLive($carrier)) {
            $label = \App\Enums\Carrier::tryFrom($carrier)?->label() ?? $carrier;

            return redirect()->route('shipments.mailings.show', $mailing->ulid)
                ->with('success', "Shipment created. {$label} isn't auto-tracked — set its status by hand and upload the bill of lading or labels.");
        }

        try {
            // notify:false — don't alert on the initial population the user just created.
            $this->tracking->refresh($mailing, notify: false);
        } catch (\Throwable $e) {
            $label = \App\Enums\Carrier::tryFrom($carrier)?->label() ?? 'carrier';

            return redirect()->route('shipments.mailings.show', $mailing->ulid)
                ->with('warning', "Mailing created, but the first {$label} lookup failed. It will retry on the next poll.");
        }

        return redirect()->route('shipments.mailings.show', $mailing->ulid)
            ->with('success', 'Mailing created and tracked.');
    }

    public function bulkCreate(Request $request): Response
    {
        $this->authorize('create', ProposalMailing::class);

        return Inertia::render('Shipments/Mailings/Bulk', [
            'carrierOptions' => $this->carrierOptions($request->user()->organization_id),
            'referenceTypeOptions' => \App\Enums\JbHuntReferenceType::options(),
        ]);
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

        $data = $request->validate([
            'tracking_numbers' => ['required', 'string', 'max:20000'],
            'carrier' => ['nullable', 'string', 'max:50'],
            'reference_type' => ['nullable', Rule::enum(\App\Enums\JbHuntReferenceType::class)],
            'scope' => ['nullable', Rule::enum(\App\Enums\ShipmentScope::class)],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'deadline' => ['nullable', 'date'],
        ]);

        $carrier = $this->normalizeCarrier($data['carrier'] ?? null);
        $referenceType = $data['reference_type'] ?? null;
        $scope = $data['scope'] ?? 'domestic';

        // Split on whitespace/commas, drop separators/labels, dedupe.
        $numbers = collect(preg_split('/[\s,]+/', $data['tracking_numbers']))
            ->map(fn ($n) => trim($n))->filter()->unique()->values();

        $capped = $numbers->count() > 100;

        $rows = $numbers->take(100)->map(fn ($tn) => [
            'tracking_number' => $tn,
            'carrier' => $carrier,
            'reference_type' => $referenceType,
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

        return Inertia::render('Shipments/Mailings/Import', [
            'carrierOptions' => $this->carrierOptions($request->user()->organization_id),
            'referenceTypeOptions' => \App\Enums\JbHuntReferenceType::options(),
        ]);
    }

    /**
     * Dump documents (CSV, label PDFs, photos of labels) and/or paste tracking
     * numbers; we read the shipping info out of each and create the shipments.
     */
    public function importStore(Request $request, \App\Services\Mailings\ShipmentImportService $importer): RedirectResponse
    {
        $this->authorize('create', ProposalMailing::class);
        $orgId = $request->user()->organization_id;

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
            'carrier' => ['nullable', 'string', 'max:50'],
            'reference_type' => ['nullable', Rule::enum(\App\Enums\JbHuntReferenceType::class)],
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
            'carrier' => $this->normalizeCarrier($request->input('carrier')),
            'reference_type' => $request->input('reference_type'),
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
            $mailing->reference_type = $this->normalizeReferenceType($mailing->carrier, $row['reference_type'] ?? null);
            // Carriers without a live integration can't be auto-synced — track manually.
            if (! $this->carrierIsLive($mailing->carrier) && \Illuminate\Support\Facades\Schema::hasColumn('proposal_mailings', 'auto_track')) {
                $mailing->auto_track = false;
            }
            $mailing->save();

            $created[] = $mailing;
        }

        $budgetUntil = microtime(true) + 20.0;
        $pending = 0;
        foreach ($created as $mailing) {
            // Manual carriers (no live integration) aren't fetched — nothing to load.
            if (! $this->carrierIsLive($mailing->carrier)) {
                continue;
            }
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
            'carrierOptions' => $this->carrierOptions($request->user()->organization_id),
            'referenceTypeOptions' => \App\Enums\JbHuntReferenceType::options(),
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

        $data = $request->validate([
            'ups_tracking_number' => [
                'required', 'string', 'max:64',
                Rule::unique('proposal_mailings', 'ups_tracking_number')
                    ->where('organization_id', $orgId)->ignore($mailing->id),
            ],
            'carrier' => ['nullable', 'string', 'max:50'],
            'reference_type' => ['nullable', Rule::enum(\App\Enums\JbHuntReferenceType::class)],
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
        $mailing->carrier = $this->normalizeCarrier($data['carrier'] ?? $mailing->carrier);
        $mailing->reference_type = $this->normalizeReferenceType($mailing->carrier, $data['reference_type'] ?? null);
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
        // the next poll. Resilient to the column not existing yet. Carriers without
        // a live integration can never auto-sync, so the flag is forced off.
        if (\Illuminate\Support\Facades\Schema::hasColumn('proposal_mailings', 'auto_track')) {
            $mailing->auto_track = $this->carrierIsLive($mailing->carrier) && $request->boolean('auto_track');
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

        if (! $this->carrierIsLive($mailing->carrier)) {
            $label = \App\Enums\Carrier::tryFrom($mailing->carrier)?->label() ?: ($mailing->carrier ?: 'This carrier');

            return back()->with('warning', "{$label} isn't auto-tracked — update its status by hand from Edit.");
        }

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

        [$updated, $skipped, $any] = $this->refreshActiveShipments($orgId);

        if (! $any) {
            return back()->with('warning', 'No active shipments to update.');
        }

        $msg = "Updated {$updated} shipment(s) from the carrier".($skipped ? " · {$skipped} left for the next run" : '').'.';

        return back()->with($updated ? 'success' : 'warning', $updated ? $msg : 'No new carrier data right now.');
    }

    /**
     * Dashboard "Sync now": pull shipments whose label was just created directly
     * from the carrier account (UPS Quantum View), then refresh tracking on the
     * active ones. The carrier pull runs ONLY when Quantum View is truly
     * configured — otherwise the simulator would fabricate shipments, so we skip
     * it and just refresh (never invent data on an unconfigured deployment).
     */
    public function sync(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', ProposalMailing::class);
        $orgId = $request->user()->organization_id;

        $created = 0;
        $pulled = false;
        if ($this->quantumViewConfigured()) {
            $pulled = true;
            try {
                $result = $this->ingest->ingest(now()->subHours(72));
                $created = $result['created'];
            } catch (\Throwable $e) {
                $pulled = false; // carrier pull failed; the refresh below still runs
            }
        }

        [$updated, $skipped, $any] = $this->refreshActiveShipments($orgId);

        $parts = [];
        if ($created > 0) {
            $parts[] = "pulled {$created} new label".($created === 1 ? '' : 's');
        }
        if ($updated > 0) {
            $parts[] = "updated {$updated} shipment".($updated === 1 ? '' : 's');
        }
        if ($skipped > 0) {
            $parts[] = "{$skipped} left for the next run";
        }

        if ($parts !== []) {
            return back()->with('success', 'Synced with the carrier — '.implode(' · ', $parts).'.');
        }

        // Nothing changed. Hint at why the label pull didn't run, when relevant.
        $msg = $any || $pulled
            ? 'Already up to date — no new carrier data right now.'
            : 'Nothing to sync yet. New labels are pulled automatically once UPS Quantum View is connected (set UPS_QV_* in the app env).';

        return back()->with('warning', $msg);
    }

    /**
     * Refresh tracking for the org's active, auto-tracked shipments within a wall-
     * clock budget. @return array{0:int,1:int,2:bool} [updated, skipped, hadAny]
     */
    private function refreshActiveShipments(int $orgId, float $budgetSeconds = 22.0): array
    {
        $query = ProposalMailing::query()->forOrganization($orgId)->active();
        if (\Illuminate\Support\Facades\Schema::hasColumn('proposal_mailings', 'auto_track')) {
            $query->where('auto_track', true);
        }
        $mailings = $query->orderBy('id')->limit(150)->get();

        if ($mailings->isEmpty()) {
            return [0, 0, false];
        }

        $budgetUntil = microtime(true) + $budgetSeconds;
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

        return [$updated, $skipped, true];
    }

    /** Whether UPS Quantum View is really configured (real client, not the simulator). */
    private function quantumViewConfigured(): bool
    {
        $ups = config('services.ups');
        $qv = $ups['quantum_view'] ?? [];

        return (bool) (($qv['enabled'] ?? false)
            && ($ups['client_id'] ?? null) && ($ups['client_secret'] ?? null) && ($qv['subscription'] ?? null));
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

    /**
     * Refresh tracking for a hand-picked set of shipments (list checkboxes →
     * "Refresh"). Live carriers only — manual ones are skipped — and bounded by a
     * wall-clock budget so a large selection can't run past the request timeout.
     */
    public function bulkRefresh(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', ProposalMailing::class);
        $orgId = $request->user()->organization_id;

        $data = $request->validate([
            'ulids' => ['required', 'array', 'max:200'],
            'ulids.*' => ['string'],
        ]);

        $mailings = ProposalMailing::query()->forOrganization($orgId)
            ->whereIn('ulid', $data['ulids'])->get();

        $budgetUntil = microtime(true) + 22.0;
        $updated = 0;
        $skipped = 0;
        foreach ($mailings as $m) {
            if (! $this->carrierIsLive($m->carrier) || microtime(true) >= $budgetUntil) {
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

        return back()->with($updated ? 'success' : 'warning',
            $updated
                ? "Refreshed {$updated} shipment(s)".($skipped ? " · {$skipped} skipped (manual carrier or left for the next run)" : '').'.'
                : 'Nothing to refresh — the selected shipments are tracked manually.');
    }

    /** Reassign a set of shipments to a different carrier (list checkboxes → "Reassign"). */
    public function bulkReassign(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', ProposalMailing::class);
        $orgId = $request->user()->organization_id;

        $data = $request->validate([
            'ulids' => ['required', 'array', 'max:500'],
            'ulids.*' => ['string'],
            'carrier' => ['required', 'string', 'max:50'],
        ]);

        $carrier = $this->normalizeCarrier($data['carrier']);
        $mailings = ProposalMailing::query()->forOrganization($orgId)
            ->whereIn('ulid', $data['ulids'])->get();

        $hasAutoTrack = \Illuminate\Support\Facades\Schema::hasColumn('proposal_mailings', 'auto_track');
        foreach ($mailings as $m) {
            $m->carrier = $carrier;
            $m->reference_type = $this->normalizeReferenceType($carrier, $m->reference_type);
            if ($hasAutoTrack && ! $this->carrierIsLive($carrier)) {
                $m->auto_track = false;
            }
            $m->save();
        }

        $label = \App\Enums\Carrier::tryFrom($carrier)?->label() ?? $carrier;

        return back()->with('success', 'Reassigned '.$mailings->count()." shipment(s) to {$label}.");
    }

    /** Delete a set of shipments (list checkboxes → "Delete"). Soft-deletes. */
    public function bulkDelete(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', ProposalMailing::class);
        $orgId = $request->user()->organization_id;

        $data = $request->validate([
            'ulids' => ['required', 'array', 'max:500'],
            'ulids.*' => ['string'],
        ]);

        $deleted = ProposalMailing::query()->forOrganization($orgId)
            ->whereIn('ulid', $data['ulids'])->delete();

        return back()->with($deleted ? 'success' : 'warning',
            $deleted ? "Deleted {$deleted} shipment(s)." : 'No shipments were deleted.');
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

    /**
     * Carriers offered in the create/edit pickers: the known enum carriers plus
     * any custom carrier names already used in this org (so a name added once is
     * reusable). There is no carriers table — `carrier` is a free string column.
     *
     * @return array<int, array{value:string, label:string}>
     */
    private function carrierOptions(int $orgId): array
    {
        // Carriers hidden ("removed") on the Carriers page aren't offered — unless a
        // shipment still uses one, which the "used" loop below adds back.
        $org = \App\Models\Organization::find($orgId);
        $hidden = $org ? $this->carrierRegistry->hidden($org) : [];

        $options = [];
        foreach (\App\Enums\Carrier::cases() as $c) {
            if (in_array($c->value, $hidden, true)) {
                continue;
            }
            $options[$c->value] = $c->label();
        }

        // Custom carriers registered on the Carriers page (even with no shipments yet).
        if ($org) {
            foreach ($this->carrierRegistry->names($org) as $name) {
                if (! isset($options[$name])) {
                    $options[$name] = $name;
                }
            }
        }

        // Plus any carrier already used on a shipment.
        $used = ProposalMailing::query()->forOrganization($orgId)
            ->whereNotNull('carrier')->distinct()->pluck('carrier');
        foreach ($used as $value) {
            $value = (string) $value;
            if ($value !== '' && ! isset($options[$value])) {
                $options[$value] = \App\Enums\Carrier::tryFrom($value)?->label() ?? $value;
            }
        }

        return collect($options)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all();
    }

    /**
     * Map a typed/selected carrier to its stored value: a known carrier label or
     * value collapses to its enum value (so UPS stays 'ups'); anything else is a
     * trimmed custom label (e.g. 'J.B. Hunt').
     */
    private function normalizeCarrier(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 'ups';
        }

        // Punctuation/spacing-insensitive form so "R&L Carriers", "R+L Carriers"
        // and "RL Carriers" all collapse to the built-in rlcarriers value.
        $compact = preg_replace('/[^a-z0-9]/', '', strtolower($value));

        foreach (\App\Enums\Carrier::cases() as $c) {
            if (strcasecmp($c->value, $value) === 0 || strcasecmp($c->label(), $value) === 0) {
                return $c->value;
            }
            $labelCompact = preg_replace('/[^a-z0-9]/', '', strtolower($c->label()));
            if ($compact !== '' && ($compact === $c->value || $compact === $labelCompact)) {
                return $c->value;
            }
        }

        return mb_substr($value, 0, 50);
    }

    /** Only carriers with a live tracking integration are auto-synced; the rest are manual. */
    private function carrierIsLive(string $carrier): bool
    {
        $enum = \App\Enums\Carrier::tryFrom($carrier);
        if ($enum === null || ! $enum->supported()) {
            return false;
        }

        // J.B. Hunt has no production simulator (unlike UPS), so it only counts as
        // live once credentialed — otherwise it stays manual (status + documents by
        // hand) rather than fabricating data or failing every poll. Mirrors
        // TrackingClientFactory::jbHunt() exactly: the fake only drives tests.
        if ($enum === \App\Enums\Carrier::JbHunt) {
            $jbh = config('services.jbhunt');
            $credentialed = (bool) ($jbh['sync_enabled'] && $jbh['client_id'] && $jbh['client_secret']);

            return $credentialed || app()->runningUnitTests();
        }

        return true;
    }

    /**
     * Reference types only apply to carriers that use them (J.B. Hunt). For any
     * other carrier it's null; for J.B. Hunt an unknown/empty value falls back to
     * the default so the tracking link always resolves.
     */
    private function normalizeReferenceType(string $carrier, ?string $value): ?string
    {
        if ($carrier !== \App\Enums\Carrier::JbHunt->value) {
            return null;
        }

        return \App\Enums\JbHuntReferenceType::tryFrom((string) $value)?->value
            ?? \App\Enums\JbHuntReferenceType::Default->value;
    }

    private function present(ProposalMailing $m, bool $withTimeline = false): array
    {
        $risk = $m->risk();

        $data = [
            'id' => $m->id,
            'ulid' => $m->ulid,
            'ups_tracking_number' => $m->ups_tracking_number,
            'carrier' => $m->carrier,
            // Known carrier → its label; a custom carrier is already a human name.
            'carrier_label' => \App\Enums\Carrier::tryFrom($m->carrier)?->label() ?: ($m->carrier ?: 'Carrier'),
            'tracking_url' => \App\Enums\Carrier::tryFrom($m->carrier)?->trackingUrl($m->ups_tracking_number, $m->reference_type),
            'reference_type' => $m->reference_type,
            'reference_type_label' => $m->reference_type
                ? (\App\Enums\JbHuntReferenceType::tryFrom($m->reference_type)?->label() ?? $m->reference_type)
                : null,
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
            'label_created_at' => optional($m->labelCreatedEvent?->occurred_at)->toDateString(),
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
