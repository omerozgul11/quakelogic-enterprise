<?php

namespace App\Http\Controllers\Web;

use App\Enums\Carrier;
use App\Enums\RateQuoteStatus;
use App\Enums\ShipmentServiceLine;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\ProposalMailing;
use App\Models\ShipmentRateQuote;
use App\Services\Mailings\CarrierRegistry;
use App\Services\Rating\DhlRateCard;
use App\Services\Rating\EstimateInput;
use App\Services\Rating\RateEstimationException;
use App\Services\Rating\RateEstimationService;
use App\Services\Rating\RateSheetExtractionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Shipment rate / spot-price quotes (/shipments/rates). Spot rates have NO carrier
 * API — you email the carrier for a quote and they email back a PDF rate sheet.
 * So this section: captures the lane + package, helps compose the request email
 * (the UI opens a prefilled draft), records the returned PDF, and uses the AI
 * extractor to pre-fill the price/transit/validity from that PDF for review.
 * Gated by `access shipments` on the route group; everything is org-scoped.
 */
class RateQuoteController extends Controller
{
    /** Accessorial services offered on the freight quote form. */
    private const ACCESSORIALS = [
        'liftgate_pickup' => 'Liftgate at pickup',
        'liftgate_delivery' => 'Liftgate at delivery',
        'residential_pickup' => 'Residential pickup',
        'residential_delivery' => 'Residential delivery',
        'inside_delivery' => 'Inside delivery',
        'appointment' => 'Delivery appointment',
        'limited_access' => 'Limited access',
    ];

    public function __construct(
        private readonly RateSheetExtractionService $extractor,
        private readonly CarrierRegistry $registry,
        private readonly RateEstimationService $estimator,
        private readonly DhlRateCard $card,
    ) {}

    public function index(Request $request): Response
    {
        $orgId = $request->user()->organization_id;
        $search = trim((string) $request->query('q', ''));
        $carrier = (string) $request->query('carrier', '');
        $status = (string) $request->query('status', '');

        $quotes = ShipmentRateQuote::query()
            ->forOrganization($orgId)
            ->with('proposalMailing:id,ulid,ups_tracking_number')
            ->when($carrier !== '', fn ($q) => $q->where('carrier', $carrier))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(function ($w) use ($like) {
                    $w->where('reference', 'like', $like)
                        ->orWhere('quote_reference', 'like', $like)
                        ->orWhere('origin_city', 'like', $like)
                        ->orWhere('dest_city', 'like', $like)
                        ->orWhere('service_level', 'like', $like);
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Shipments/Rates/Index', [
            'quotes' => $quotes->through(fn (ShipmentRateQuote $q) => $this->toRow($q)),
            'filters' => ['q' => $search, 'carrier' => $carrier, 'status' => $status],
            'carrierOptions' => $this->carrierOptions($this->hiddenCarriers($orgId)),
            'statusOptions' => $this->statusOptions(),
            'stats' => [
                'total' => ShipmentRateQuote::forOrganization($orgId)->count(),
                'quoted' => ShipmentRateQuote::forOrganization($orgId)->where('status', RateQuoteStatus::Quoted->value)->count(),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Shipments/Rates/Create', $this->formProps($request) + ['quote' => null]);
    }

    public function edit(Request $request, string $ulid): Response
    {
        $quote = $this->find($request, $ulid);

        return Inertia::render('Shipments/Rates/Create', $this->formProps($request) + [
            'quote' => $this->toForm($quote),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateQuote($request);
        $orgId = $request->user()->organization_id;

        $quote = new ShipmentRateQuote($this->attributes($data, $orgId));
        $quote->organization_id = $orgId;
        $quote->created_by = $request->user()->id;
        $quote->save();

        return redirect()->route('shipments.rates.edit', $quote->ulid)
            ->with('success', 'Rate request saved. Email the carrier, then attach the PDF they send back.');
    }

    public function update(Request $request, string $ulid): RedirectResponse
    {
        $quote = $this->find($request, $ulid);
        $quote->fill($this->attributes($this->validateQuote($request), $quote->organization_id));
        $quote->save();

        return redirect()->route('shipments.rates.index')->with('success', 'Rate request updated.');
    }

    /** Mark that the request email has gone out (the draft is composed client-side). */
    public function markRequested(Request $request, string $ulid): RedirectResponse
    {
        $quote = $this->find($request, $ulid);
        $quote->requested_at = now();
        if ($quote->status === RateQuoteStatus::Draft) {
            $quote->status = RateQuoteStatus::Requested;
        }
        $quote->save();

        return back()->with('success', 'Marked as requested — attach the PDF here once the carrier replies.');
    }

    public function destroy(Request $request, string $ulid): RedirectResponse
    {
        $quote = $this->find($request, $ulid);
        $this->deleteStoredDocument($quote);
        $quote->delete();

        return redirect()->route('shipments.rates.index')->with('success', 'Rate request removed.');
    }

    // ---- rate-sheet PDF ---------------------------------------------------

    public function uploadDocument(Request $request, string $ulid): RedirectResponse
    {
        $quote = $this->find($request, $ulid);

        $request->validate([
            'file' => 'required|file|max:51200|mimetypes:application/pdf,image/jpeg,image/png|mimes:pdf,jpg,jpeg,png',
        ]);

        $this->deleteStoredDocument($quote);

        $file = $request->file('file');
        $storedName = (string) Str::ulid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs("rate-quote-documents/{$quote->id}", $storedName, 'local');

        $quote->fill([
            'document_path' => $path,
            'document_name' => $file->getClientOriginalName(),
            'document_mime' => $file->getMimeType(),
            'document_size' => $file->getSize(),
            'document_uploaded_at' => now(),
        ])->save();

        $found = $this->applyExtraction($quote);

        $msg = 'Rate sheet attached.';
        $msg .= $found > 0
            ? " Pulled {$found} field".($found === 1 ? '' : 's')." from it — review and save."
            : " Couldn't read it automatically — enter the figures by hand.";

        return back()->with($found > 0 ? 'success' : 'warning', $msg);
    }

    /** Re-run extraction on the already-attached PDF. */
    public function extract(Request $request, string $ulid): RedirectResponse
    {
        $quote = $this->find($request, $ulid);

        if (! $quote->hasDocument()) {
            return back()->with('warning', 'Attach a rate-sheet PDF first.');
        }

        $found = $this->applyExtraction($quote);

        return back()->with($found > 0 ? 'success' : 'warning', $found > 0
            ? "Pulled {$found} field".($found === 1 ? '' : 's')." from the rate sheet — review and save."
            : "Couldn't read the rate sheet automatically — enter the figures by hand.");
    }

    public function downloadDocument(Request $request, string $ulid): mixed
    {
        $quote = $this->find($request, $ulid);
        abort_unless($quote->hasDocument() && Storage::disk('local')->exists($quote->document_path), 404);

        return Storage::disk('local')->download($quote->document_path, $quote->document_name ?: 'rate-sheet.pdf');
    }

    public function deleteDocument(Request $request, string $ulid): RedirectResponse
    {
        $quote = $this->find($request, $ulid);
        $this->deleteStoredDocument($quote);
        $quote->fill([
            'document_path' => null, 'document_name' => null, 'document_mime' => null,
            'document_size' => null, 'document_uploaded_at' => null,
        ])->save();

        return back()->with('success', 'Rate sheet removed.');
    }

    // ---- instant estimate (DHL contract rate card) ------------------------

    /**
     * Price a shipment from QuakeLogic's DHL Express contract card without emailing
     * for a spot quote. Pure computation — returns JSON the Create form reads to
     * pre-fill the price/service/transit and show a breakdown. Known-bad inputs
     * (unknown country, non-US origin, no weight) come back as a 422 with a message.
     */
    public function estimate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'origin_country' => ['nullable', 'string', 'size:2'],
            'dest_country' => ['required', 'string', 'size:2'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'weight_unit' => ['nullable', Rule::in(['lb', 'kg'])],
            'length' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'width' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'height' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'dim_unit' => ['nullable', Rule::in(['in', 'cm'])],
            'content_type' => ['nullable', Rule::in(['package', 'document'])],
            'discount_pct' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'premium' => ['nullable', Rule::in(['9', '12'])],
        ]);

        try {
            $estimate = $this->estimator->estimate(new EstimateInput(
                originCountry: strtoupper($data['origin_country'] ?? 'US'),
                destCountry: $data['dest_country'],
                weight: (float) ($data['weight'] ?? 0),
                weightUnit: $data['weight_unit'] ?? 'lb',
                length: isset($data['length']) ? (float) $data['length'] : null,
                width: isset($data['width']) ? (float) $data['width'] : null,
                height: isset($data['height']) ? (float) $data['height'] : null,
                dimUnit: $data['dim_unit'] ?? 'in',
                contentType: $data['content_type'] ?? 'package',
                discountPct: isset($data['discount_pct']) ? (float) $data['discount_pct'] : null,
                premium: $data['premium'] ?? null,
            ));
        } catch (RateEstimationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['estimate' => $estimate->toArray()]);
    }

    // ---- helpers ----------------------------------------------------------

    private function find(Request $request, string $ulid): ShipmentRateQuote
    {
        return ShipmentRateQuote::query()
            ->forOrganization($request->user()->organization_id)
            ->where('ulid', $ulid)
            ->firstOrFail();
    }

    private function deleteStoredDocument(ShipmentRateQuote $quote): void
    {
        if ($quote->document_path && Storage::disk('local')->exists($quote->document_path)) {
            Storage::disk('local')->delete($quote->document_path);
        }
    }

    /** Read the attached PDF and pre-fill the recognised fields. Returns the count filled. */
    private function applyExtraction(ShipmentRateQuote $quote): int
    {
        $fields = $this->extractor->extract($quote);
        if ($fields === []) {
            return 0;
        }

        // A price coming back means we have a quote — advance an early-stage row.
        if (isset($fields['amount']) && in_array($quote->status, [RateQuoteStatus::Draft, RateQuoteStatus::Requested], true)) {
            $fields['status'] = RateQuoteStatus::Quoted->value;
            $fields['quoted_at'] = now();
        }

        $quote->fill($fields)->save();

        return count(array_intersect_key($fields, array_flip([
            'amount', 'currency', 'service_level', 'transit_days', 'estimated_delivery',
            'expires_at', 'quote_reference', 'weight', 'origin_city', 'origin_postal', 'dest_city', 'dest_postal',
        ])));
    }

    private function validateQuote(Request $request): array
    {
        return $request->validate([
            'reference' => ['nullable', 'string', 'max:120'],
            'contact_email' => ['nullable', 'email', 'max:160'],
            'carrier' => ['required', 'string', 'max:50'],
            'service_line' => ['nullable', Rule::in(array_map(fn ($c) => $c->value, ShipmentServiceLine::cases()))],
            'status' => ['nullable', Rule::in(array_map(fn ($c) => $c->value, RateQuoteStatus::cases()))],
            'proposal_mailing_id' => ['nullable', 'integer'],

            'origin_city' => ['nullable', 'string', 'max:120'],
            'origin_state' => ['nullable', 'string', 'max:60'],
            'origin_postal' => ['nullable', 'string', 'max:20'],
            'origin_country' => ['nullable', 'string', 'size:2'],
            'dest_city' => ['nullable', 'string', 'max:120'],
            'dest_state' => ['nullable', 'string', 'max:60'],
            'dest_postal' => ['nullable', 'string', 'max:20'],
            'dest_country' => ['nullable', 'string', 'size:2'],
            'ready_date' => ['nullable', 'date'],
            'service_level' => ['nullable', 'string', 'max:120'],

            'weight' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'weight_unit' => ['nullable', Rule::in(['lb', 'kg'])],
            'length' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'width' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'height' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'dim_unit' => ['nullable', Rule::in(['in', 'cm'])],

            'freight_class' => ['nullable', 'string', 'max:10'],
            'pallet_count' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'piece_count' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'accessorials' => ['nullable', 'array'],
            'accessorials.*' => ['string', Rule::in(array_keys(self::ACCESSORIALS))],

            'amount' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'currency' => ['nullable', 'string', 'size:3'],
            'transit_days' => ['nullable', 'integer', 'min:0', 'max:999'],
            'estimated_delivery' => ['nullable', 'date'],
            'quote_reference' => ['nullable', 'string', 'max:120'],
            'expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    /** Map validated input onto model attributes (carrier normalized, link verified). */
    private function attributes(array $data, int $orgId): array
    {
        $mailingId = null;
        if (! empty($data['proposal_mailing_id'])) {
            $mailingId = ProposalMailing::forOrganization($orgId)
                ->whereKey($data['proposal_mailing_id'])
                ->value('id');
        }

        return [
            'reference' => $data['reference'] ?? null,
            'contact_email' => $data['contact_email'] ?? null,
            'carrier' => $this->normalizeCarrier($data['carrier']),
            'service_line' => $data['service_line'] ?? null,
            'status' => $data['status'] ?? RateQuoteStatus::Draft->value,
            'proposal_mailing_id' => $mailingId,

            'origin_city' => $data['origin_city'] ?? null,
            'origin_state' => $data['origin_state'] ?? null,
            'origin_postal' => $data['origin_postal'] ?? null,
            'origin_country' => strtoupper($data['origin_country'] ?? 'US'),
            'dest_city' => $data['dest_city'] ?? null,
            'dest_state' => $data['dest_state'] ?? null,
            'dest_postal' => $data['dest_postal'] ?? null,
            'dest_country' => strtoupper($data['dest_country'] ?? 'US'),
            'ready_date' => $data['ready_date'] ?? null,
            'service_level' => $data['service_level'] ?? null,

            'weight' => $data['weight'] ?? null,
            'weight_unit' => $data['weight_unit'] ?? 'lb',
            'length' => $data['length'] ?? null,
            'width' => $data['width'] ?? null,
            'height' => $data['height'] ?? null,
            'dim_unit' => $data['dim_unit'] ?? 'in',

            'freight_class' => $data['freight_class'] ?? null,
            'pallet_count' => $data['pallet_count'] ?? null,
            'piece_count' => $data['piece_count'] ?? null,
            'accessorials' => array_values($data['accessorials'] ?? []) ?: null,

            'amount' => $data['amount'] ?? null,
            'currency' => strtoupper($data['currency'] ?? 'USD'),
            'transit_days' => $data['transit_days'] ?? null,
            'estimated_delivery' => $data['estimated_delivery'] ?? null,
            'quote_reference' => $data['quote_reference'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];
    }

    /** Collapse "DHL"/"dhl"/an enum label to the enum value; keep custom names as typed. */
    private function normalizeCarrier(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return Carrier::Dhl->value;
        }

        $compact = preg_replace('/[^a-z0-9]/', '', strtolower($value));
        foreach (Carrier::cases() as $c) {
            $labelCompact = preg_replace('/[^a-z0-9]/', '', strtolower($c->label()));
            if ($compact !== '' && ($compact === $c->value || $compact === $labelCompact)) {
                return $c->value;
            }
        }

        return mb_substr($value, 0, 50);
    }

    private function formProps(Request $request): array
    {
        $orgId = $request->user()->organization_id;

        $shipments = ProposalMailing::query()
            ->forOrganization($orgId)
            ->latest()
            ->limit(100)
            ->get(['id', 'ups_tracking_number', 'recipient_name', 'carrier']);

        return [
            'carrierOptions' => $this->carrierOptions($this->hiddenCarriers($orgId)),
            'serviceLineOptions' => array_map(
                fn (ShipmentServiceLine $s) => ['value' => $s->value, 'label' => $s->label()],
                ShipmentServiceLine::cases(),
            ),
            'statusOptions' => $this->statusOptions(),
            'accessorialOptions' => array_map(
                fn ($key) => ['value' => $key, 'label' => self::ACCESSORIALS[$key]],
                array_keys(self::ACCESSORIALS),
            ),
            'linkableShipments' => $shipments->map(fn ($m) => [
                'id' => $m->id,
                'label' => trim(($m->ups_tracking_number ?? '').' — '.($m->recipient_name ?: 'Unnamed'), ' —'),
            ])->values(),
            'rateCard' => $this->rateCardProps(),
        ];
    }

    /** Rate-card metadata + revenue-based discount tiers for the instant-estimate panel. */
    private function rateCardProps(): array
    {
        if (! $this->card->isLoaded()) {
            return ['available' => false];
        }

        return [
            'available' => true,
            'product' => $this->card->product(),
            'currency' => $this->card->currency(),
            'origin_country' => $this->card->originCountry(),
            'as_of' => $this->card->asOf(),
            'discount_tiers' => array_map(fn ($t) => [
                'pct' => $t['pct'],
                'label' => $this->tierLabel($t).' → '.round($t['pct'] * 100).'%',
            ], $this->card->discountTiers('export')),
        ];
    }

    /** "$0–$50/mo", "$30,000–$40,000/mo", or "Above $40,000/mo" for a discount tier. */
    private function tierLabel(array $tier): string
    {
        $money = fn ($n) => '$'.number_format((float) $n);

        return ($tier['max'] ?? null) === null
            ? 'Above '.$money($tier['min']).'/mo'
            : $money($tier['min']).'–'.$money($tier['max']).'/mo';
    }

    /** @param  list<string>  $hidden  carrier values removed on the Carriers page */
    private function carrierOptions(array $hidden = []): array
    {
        // DHL first — it's the carrier this section is built around. Hidden carriers
        // are dropped to stay consistent with the Carriers page.
        $cases = collect(Carrier::cases())
            ->reject(fn (Carrier $c) => in_array($c->value, $hidden, true))
            ->sortBy(fn (Carrier $c) => $c === Carrier::Dhl ? 0 : 1)
            ->values();

        return $cases->map(fn (Carrier $c) => ['value' => $c->value, 'label' => $c->label()])->all();
    }

    private function hiddenCarriers(int $orgId): array
    {
        $org = Organization::find($orgId);

        return $org ? $this->registry->hidden($org) : [];
    }

    private function statusOptions(): array
    {
        return array_map(
            fn (RateQuoteStatus $s) => ['value' => $s->value, 'label' => $s->label()],
            RateQuoteStatus::cases(),
        );
    }

    private function toRow(ShipmentRateQuote $q): array
    {
        $status = $q->status instanceof RateQuoteStatus ? $q->status : RateQuoteStatus::Draft;

        return [
            'ulid' => $q->ulid,
            'reference' => $q->reference,
            'carrier' => $q->carrier,
            'carrier_label' => Carrier::tryFrom($q->carrier)?->label() ?? $q->carrier,
            'service_line' => $q->service_line?->value,
            'service_line_label' => $q->service_line?->label(),
            'status' => $status->value,
            'status_label' => $status->label(),
            'status_color' => $status->color(),
            'origin' => $q->originLabel(),
            'destination' => $q->destinationLabel(),
            'amount' => $q->amount !== null ? (float) $q->amount : null,
            'currency' => $q->currency,
            'transit_days' => $q->transit_days,
            'estimated_delivery' => $q->estimated_delivery?->toDateString(),
            'expires_at' => $q->expires_at?->toIso8601String(),
            'is_expired' => $q->isExpired(),
            'quote_reference' => $q->quote_reference,
            'service_level' => $q->service_level,
            'source' => $q->source,
            'has_document' => $q->hasDocument(),
            'mailing' => $q->proposalMailing ? [
                'ulid' => $q->proposalMailing->ulid,
                'tracking' => $q->proposalMailing->ups_tracking_number,
            ] : null,
            'created_at' => $q->created_at?->toIso8601String(),
        ];
    }

    /** Shape a quote for the create/edit form (form-friendly strings + attachment info). */
    private function toForm(ShipmentRateQuote $q): array
    {
        $s = fn ($v) => $v === null ? '' : (string) $v;

        return [
            'ulid' => $q->ulid,
            'reference' => $s($q->reference),
            'contact_email' => $s($q->contact_email),
            'carrier' => $q->carrier,
            'service_line' => $q->service_line?->value ?? '',
            'status' => $q->status?->value ?? RateQuoteStatus::Draft->value,
            'proposal_mailing_id' => $q->proposal_mailing_id ? (string) $q->proposal_mailing_id : '',
            'origin_city' => $s($q->origin_city),
            'origin_state' => $s($q->origin_state),
            'origin_postal' => $s($q->origin_postal),
            'origin_country' => $q->origin_country ?: 'US',
            'dest_city' => $s($q->dest_city),
            'dest_state' => $s($q->dest_state),
            'dest_postal' => $s($q->dest_postal),
            'dest_country' => $q->dest_country ?: 'US',
            'ready_date' => $q->ready_date?->toDateString() ?? '',
            'service_level' => $s($q->service_level),
            'weight' => $s($q->weight !== null ? (float) $q->weight : null),
            'weight_unit' => $q->weight_unit ?: 'lb',
            'length' => $s($q->length !== null ? (float) $q->length : null),
            'width' => $s($q->width !== null ? (float) $q->width : null),
            'height' => $s($q->height !== null ? (float) $q->height : null),
            'dim_unit' => $q->dim_unit ?: 'in',
            'freight_class' => $s($q->freight_class),
            'pallet_count' => $s($q->pallet_count),
            'piece_count' => $s($q->piece_count),
            'accessorials' => $q->accessorials ?? [],
            'amount' => $s($q->amount !== null ? (float) $q->amount : null),
            'currency' => $q->currency ?: 'USD',
            'transit_days' => $s($q->transit_days),
            'estimated_delivery' => $q->estimated_delivery?->toDateString() ?? '',
            'quote_reference' => $s($q->quote_reference),
            'expires_at' => $q->expires_at?->toDateString() ?? '',
            'notes' => $s($q->notes),
            'document' => $q->hasDocument() ? [
                'name' => $q->document_name,
                'size' => $q->documentSizeLabel(),
                'uploaded_at' => $q->document_uploaded_at?->toIso8601String(),
            ] : null,
        ];
    }
}
