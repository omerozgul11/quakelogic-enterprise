<?php

namespace App\Modules\Calibration\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\AssetManagement\Models\Asset;
use App\Modules\Calibration\Enums\CalibrationResult;
use App\Modules\Calibration\Http\Requests\CalibrationRequest;
use App\Modules\Calibration\Models\CalibrationCertificate;
use App\Modules\Calibration\Services\CalibrationService;
use App\Modules\Inventory\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CertificateController extends Controller
{
    public function __construct(private readonly CalibrationService $service) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CalibrationCertificate::class);
        $orgId = $request->user()->organization_id;

        $certificates = CalibrationCertificate::where('organization_id', $orgId)
            ->with(['asset:id,asset_tag,name', 'product:id,sku'])
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('certificate_number', 'like', "%{$s}%")
                ->orWhere('serial_number', 'like', "%{$s}%")
                ->orWhereHas('asset', fn ($a) => $a->where('asset_tag', 'like', "%{$s}%")->orWhere('name', 'like', "%{$s}%"))))
            ->when($request->result, fn ($q, $r) => $q->where('result', $r))
            ->when($request->due === 'overdue', fn ($q) => $q->whereNotNull('due_at')->whereDate('due_at', '<', now()->toDateString()))
            ->when($request->due === 'soon', fn ($q) => $q->whereNotNull('due_at')->whereBetween('due_at', [now()->toDateString(), now()->addDays(30)->toDateString()]))
            ->latest('calibrated_at')->latest('id')
            ->paginate(20)->withQueryString()
            ->through(fn (CalibrationCertificate $c) => [
                'id' => $c->id,
                'certificate_number' => $c->certificate_number,
                'subject' => $c->asset ? $c->asset->asset_tag.' · '.$c->asset->name : ($c->product?->sku ?? '—'),
                'result' => $c->result->value,
                'result_label' => $c->result->label(),
                'result_color' => $c->result->color(),
                'nist_traceable' => $c->nist_traceable,
                'calibrated_at' => $c->calibrated_at?->toDateString(),
                'due_at' => $c->due_at?->toDateString(),
                'overdue' => $c->isOverdue(),
            ]);

        return Inertia::render('Calibration/Certificates/Index', [
            'certificates' => $certificates,
            'filters' => $request->only(['search', 'result', 'due']),
            'results' => CalibrationResult::options(),
            'form' => $this->formData($request),
            'can' => ['manage' => $request->user()->can('manage calibration')],
        ]);
    }

    public function show(Request $request, CalibrationCertificate $certificate): Response
    {
        $this->authorize('view', $certificate);

        $certificate->load(['asset:id,asset_tag,name', 'product:id,sku,name', 'performer:id,name', 'creator:id,name']);

        return Inertia::render('Calibration/Certificates/Show', [
            'certificate' => [
                'id' => $certificate->id,
                'certificate_number' => $certificate->certificate_number,
                'result' => $certificate->result->value,
                'result_label' => $certificate->result->label(),
                'result_color' => $certificate->result->color(),
                'nist_traceable' => $certificate->nist_traceable,
                'method' => $certificate->method,
                'standard_used' => $certificate->standard_used,
                'technician' => $certificate->technician,
                'serial_number' => $certificate->serial_number,
                'calibrated_at' => $certificate->calibrated_at?->toDateString(),
                'due_at' => $certificate->due_at?->toDateString(),
                'overdue' => $certificate->isOverdue(),
                'interval_months' => $certificate->interval_months,
                'measurements' => $certificate->measurements,
                'notes' => $certificate->notes,
                'asset' => $certificate->asset ? ['id' => $certificate->asset->id, 'asset_tag' => $certificate->asset->asset_tag, 'name' => $certificate->asset->name] : null,
                'product' => $certificate->product ? ['id' => $certificate->product->id, 'sku' => $certificate->product->sku, 'name' => $certificate->product->name] : null,
                'performer' => $certificate->performer?->name,
            ],
            'can' => ['manage' => $request->user()->can('manage calibration')],
        ]);
    }

    public function store(CalibrationRequest $request): RedirectResponse
    {
        $this->authorize('create', CalibrationCertificate::class);
        $user = $request->user();

        $certificate = $this->service->record($user->organization_id, $user->id, $request->validated());

        return redirect()->route('calibration.certificates.show', $certificate)
            ->with('success', "Calibration certificate {$certificate->certificate_number} recorded.");
    }

    public function update(CalibrationRequest $request, CalibrationCertificate $certificate): RedirectResponse
    {
        $this->authorize('update', $certificate);
        $certificate->update($request->validated());

        return back()->with('success', 'Certificate updated.');
    }

    public function destroy(Request $request, CalibrationCertificate $certificate): RedirectResponse
    {
        $this->authorize('delete', $certificate);
        $number = $certificate->certificate_number;
        $certificate->delete();

        return redirect()->route('calibration.certificates.index')->with('success', "Certificate {$number} deleted.");
    }

    /** @return array<string,mixed> */
    private function formData(Request $request): array
    {
        $orgId = $request->user()->organization_id;

        return [
            'assets' => Asset::where('organization_id', $orgId)->orderBy('asset_tag')->get(['id', 'asset_tag', 'name', 'serial_number']),
            'products' => Product::where('organization_id', $orgId)->where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name']),
            'users' => User::where('organization_id', $orgId)->orderBy('name')->get(['id', 'name']),
        ];
    }
}
