<?php

namespace App\Http\Controllers\Web;

use App\Enums\ComplianceStatus;
use App\Enums\ComplianceType;
use App\Http\Controllers\Controller;
use App\Models\ComplianceItem;
use App\Services\Ai\AiProviderInterface;
use App\Services\Documents\DocumentTextExtractionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase 7 — company/org compliance register (W-9, insurance, ISO, SAM, CAGE,
 * UEI, NDA, vendor registrations) with identifiers and expiry tracking.
 */
class ComplianceController extends Controller
{
    private const EXPIRING_WINDOW_DAYS = 45;

    public const RENEWAL_INTERVALS = ['monthly', 'quarterly', 'semiannual', 'annual', 'biennial'];

    public function index(Request $request): Response
    {
        $user = $request->user();

        $items = ComplianceItem::forOrganization($user->organization_id)
            ->with('createdBy:id,name')
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $rows = $items->map(function (ComplianceItem $i) {
            $days = $i->daysUntilExpiry();
            return [
                'id' => $i->id,
                'type' => $i->type->value,
                'type_label' => $i->type->label(),
                'name' => $i->name,
                'identifier' => $i->identifier,
                'status' => $i->status->value,
                'status_label' => $i->status->label(),
                'status_color' => $i->status->color(),
                'issuer' => $i->issuer,
                'issued_at' => $i->issued_at?->format('Y-m-d'),
                'expires_at' => $i->expires_at?->format('Y-m-d'),
                'renewal_interval' => $i->renewal_interval,
                'reference_url' => $i->reference_url,
                'notes' => $i->notes,
                'days_until_expiry' => $days,
                'expiring_soon' => $days !== null && $days >= 0 && $days <= self::EXPIRING_WINDOW_DAYS,
                'is_expired' => $days !== null && $days < 0,
            ];
        })->values();

        return Inertia::render('Compliance/Index', [
            'items' => $rows,
            'types' => array_map(fn (ComplianceType $t) => ['value' => $t->value, 'label' => $t->label()], ComplianceType::cases()),
            'statuses' => array_map(fn (ComplianceStatus $s) => ['value' => $s->value, 'label' => $s->label(), 'color' => $s->color()], ComplianceStatus::cases()),
            'renewalIntervals' => array_map(fn ($r) => ['value' => $r, 'label' => ucfirst($r)], self::RENEWAL_INTERVALS),
            'summary' => [
                'total' => $rows->count(),
                'expiring_soon' => $rows->where('expiring_soon', true)->count(),
                'expired' => $rows->where('is_expired', true)->count(),
            ],
            'can' => ['manage' => $user->can('manage compliance')],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage compliance');
        $data = $this->validated($request);
        $data['organization_id'] = $request->user()->organization_id;
        $data['created_by'] = $request->user()->id;

        ComplianceItem::create($data);

        return back()->with('success', 'Compliance item added.');
    }

    public function update(Request $request, ComplianceItem $compliance): RedirectResponse
    {
        $this->authorize('manage compliance');
        abort_unless($compliance->organization_id === $request->user()->organization_id, 404);

        $compliance->update($this->validated($request));

        return back()->with('success', 'Compliance item updated.');
    }

    public function destroy(Request $request, ComplianceItem $compliance): RedirectResponse
    {
        $this->authorize('manage compliance');
        abort_unless($compliance->organization_id === $request->user()->organization_id, 404);

        $compliance->delete();

        return back()->with('success', 'Compliance item removed.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(array_map(fn ($t) => $t->value, ComplianceType::cases()))],
            'name' => 'required|string|max:255',
            'identifier' => 'nullable|string|max:255',
            'status' => ['required', Rule::in(array_map(fn ($s) => $s->value, ComplianceStatus::cases()))],
            'issuer' => 'nullable|string|max:255',
            'issued_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
            'renewal_interval' => ['nullable', Rule::in(self::RENEWAL_INTERVALS)],
            'reference_url' => 'nullable|url|max:2000',
            'notes' => 'nullable|string',
        ]);
    }

    /**
     * Drop a document (W-9, insurance cert, SAM/registration, etc.) and let the
     * AI Brain read it and pre-fill a compliance item. Created directly (then
     * editable in the table) so dropping a file "just fills it out".
     */
    public function import(Request $request): RedirectResponse
    {
        $this->authorize('manage compliance');
        $request->validate(['document' => 'required|file|max:20480']);

        $user = $request->user();
        $file = $request->file('document');
        $path = $file->store('compliance-imports', 'local');

        try {
            $text = app(DocumentTextExtractionService::class)->extract($path, $file->getMimeType() ?? '');
        } catch (\Throwable) {
            $text = '';
        } finally {
            Storage::disk('local')->delete($path);
        }

        if (trim($text) === '') {
            return back()->with('error', "Couldn't read that document — add the item manually instead.");
        }

        $parsed = $this->aiExtract($text);

        ComplianceItem::create(array_merge($parsed, [
            'organization_id' => $user->organization_id,
            'created_by' => $user->id,
        ]));

        return back()->with('success', "Imported \"{$parsed['name']}\" from the document — review and adjust the details.");
    }

    /**
     * Ask the AI Brain to pull compliance fields from raw document text. Falls
     * back to a safe default item when the model is unavailable or unsure.
     *
     * @return array<string,mixed>
     */
    private function aiExtract(string $text): array
    {
        $validTypes = implode(', ', array_map(fn ($t) => $t->value, ComplianceType::cases()));

        $system = 'You extract one compliance/registration record from a document. Return ONLY a JSON object, no prose.';
        $prompt = <<<TXT
            Read this document and return this exact JSON shape (use null when not present):
            {
              "type": one of [{$validTypes}] (best fit; use "other" if unsure),
              "name": short human label for the item (e.g. "General Liability Insurance", "SAM Registration"),
              "identifier": policy/registration/cert/CAGE/UEI/EIN number if present,
              "issuer": issuing authority or carrier,
              "issued_at": "YYYY-MM-DD" or null,
              "expires_at": "YYYY-MM-DD" or null
            }

            Document:
            {{$text}}
            TXT;

        $parsed = [];
        try {
            $raw = app(AiProviderInterface::class)->complete($system, mb_substr($prompt, 0, 14000));
            if (preg_match('/\{.*\}/s', $raw, $m)) {
                $parsed = json_decode($m[0], true) ?: [];
            }
        } catch (\Throwable) {
            $parsed = [];
        }

        $type = is_string($parsed['type'] ?? null) && in_array($parsed['type'], array_map(fn ($t) => $t->value, ComplianceType::cases()), true)
            ? $parsed['type'] : 'other';
        $date = fn ($v) => (is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) ? $v : null;

        return [
            'type' => $type,
            'name' => is_string($parsed['name'] ?? null) && $parsed['name'] !== '' ? mb_substr($parsed['name'], 0, 255) : 'Imported document',
            'identifier' => is_string($parsed['identifier'] ?? null) ? mb_substr($parsed['identifier'], 0, 255) : null,
            'issuer' => is_string($parsed['issuer'] ?? null) ? mb_substr($parsed['issuer'], 0, 255) : null,
            'issued_at' => $date($parsed['issued_at'] ?? null),
            'expires_at' => $date($parsed['expires_at'] ?? null),
            'status' => 'active',
        ];
    }
}
