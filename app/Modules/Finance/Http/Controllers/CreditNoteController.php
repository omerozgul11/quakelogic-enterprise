<?php

namespace App\Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Crm\Invoice;
use App\Modules\Finance\Models\CreditNote;
use App\Modules\Finance\Services\CreditNoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CreditNoteController extends Controller
{
    public function __construct(private readonly CreditNoteService $service) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CreditNote::class);
        $orgId = $request->user()->organization_id;

        $notes = CreditNote::where('organization_id', $orgId)
            ->with(['company:id,name', 'invoice:id,number'])
            ->when($request->search, fn ($q, $s) => $q->where('number', 'like', "%{$s}%")->orWhere('reason', 'like', "%{$s}%"))
            ->when($request->status, fn ($q, $st) => $q->where('status', $st))
            ->latest('id')
            ->paginate(20)->withQueryString()
            ->through(fn (CreditNote $n) => [
                'id' => $n->id,
                'number' => $n->number,
                'company' => $n->company?->name,
                'invoice' => $n->invoice?->number,
                'amount' => (float) $n->amount,
                'currency' => $n->currency,
                'reason' => $n->reason,
                'status' => $n->status->value,
                'status_label' => $n->status->label(),
                'status_color' => $n->status->color(),
                'issued_at' => $n->issued_at?->toDateString(),
            ]);

        return Inertia::render('Finance/CreditNotes/Index', [
            'credit_notes' => $notes,
            'filters' => $request->only(['search', 'status']),
            'statuses' => \App\Modules\Finance\Enums\CreditNoteStatus::options(),
            'form' => [
                'companies' => Company::where('organization_id', $orgId)->orderBy('name')->get(['id', 'name']),
                // total + amount_paid are needed by the model's appended `balance`.
                'invoices' => Invoice::where('organization_id', $orgId)->where('kind', 'invoice')->orderByDesc('id')->limit(200)->get(['id', 'number', 'company_id', 'total', 'amount_paid']),
            ],
            'can' => ['manage' => $request->user()->can('manage finance')],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', CreditNote::class);
        $user = $request->user();
        $orgId = $user->organization_id;

        $validated = $request->validate([
            'company_id' => ['nullable', Rule::exists('companies', 'id')->where('organization_id', $orgId)],
            'crm_invoice_id' => ['nullable', Rule::exists('crm_invoices', 'id')->where('organization_id', $orgId)->whereNull('deleted_at')],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999999'],
            'currency' => ['nullable', 'string', 'size:3'],
            'reason' => ['nullable', 'string', 'max:200'],
            'issued_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $this->service->issue($orgId, $user->id, $validated);

        return back()->with('success', 'Credit note issued.');
    }

    public function apply(Request $request, CreditNote $creditNote): RedirectResponse
    {
        $this->authorize('update', $creditNote);
        $this->service->apply($creditNote);

        return back()->with('success', "Credit note {$creditNote->number} applied.");
    }

    public function void(Request $request, CreditNote $creditNote): RedirectResponse
    {
        $this->authorize('update', $creditNote);
        $this->service->void($creditNote);

        return back()->with('success', "Credit note {$creditNote->number} voided.");
    }

    public function destroy(Request $request, CreditNote $creditNote): RedirectResponse
    {
        $this->authorize('delete', $creditNote);
        $number = $creditNote->number;
        $creditNote->delete();

        return back()->with('success', "Credit note {$number} deleted.");
    }
}
