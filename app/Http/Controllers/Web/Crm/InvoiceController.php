<?php

namespace App\Http\Controllers\Web\Crm;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Crm\Invoice;
use App\Models\Crm\Payment;
use App\Models\Crm\Project;
use App\Services\Crm\ProjectCreationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Invoice::class);
        $user = $request->user();

        $invoices = Invoice::where('organization_id', $user->organization_id)
            ->with('company:id,name')
            ->when($request->kind, fn ($q, $k) => $q->where('kind', $k))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('number', 'like', "%{$s}%")
                ->orWhereHas('company', fn ($c) => $c->where('name', 'like', "%{$s}%"))))
            ->orderByDesc('issue_date')->orderByDesc('id')
            ->paginate(15)->withQueryString();

        $base = Invoice::where('organization_id', $user->organization_id)->where('kind', 'invoice');
        $stats = [
            'outstanding' => (float) (clone $base)
                ->whereNotIn('status', [InvoiceStatus::Paid->value, InvoiceStatus::Void->value])
                ->sum(DB::raw('total - amount_paid')),
            'paid' => (float) (clone $base)->sum('amount_paid'),
            'draft_count' => (clone $base)->where('status', InvoiceStatus::Draft->value)->count(),
            'overdue_count' => (clone $base)->where('status', InvoiceStatus::Overdue->value)->count(),
        ];

        return Inertia::render('Crm/Invoices/Index', [
            'invoices' => $invoices,
            'filters' => $request->only(['search', 'kind', 'status']),
            'stats' => $stats,
            'statuses' => $this->statusOptions(),
            'can' => ['manage' => $user->can('manage invoices')],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Invoice::class);

        return Inertia::render('Crm/Invoices/Form', $this->formProps($request) + [
            'invoice' => null,
            'kind' => $request->query('kind') === 'estimate' ? 'estimate' : 'invoice',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Invoice::class);
        $user = $request->user();
        $data = $this->validateInvoice($request);

        $invoice = DB::transaction(function () use ($data, $user) {
            $invoice = Invoice::create([
                'organization_id' => $user->organization_id,
                'created_by' => $user->id,
                'owner_id' => $user->id,
                'company_id' => $data['company_id'] ?? null,
                'crm_project_id' => $data['crm_project_id'] ?? null,
                'number' => $this->generateNumber($user->organization_id, $data['kind']),
                'kind' => $data['kind'],
                'status' => $data['status'] ?? InvoiceStatus::Draft->value,
                'issue_date' => $data['issue_date'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'tax_rate' => $data['tax_rate'] ?? 0,
                'discount_amount' => $data['discount_amount'] ?? 0,
                'currency' => $data['currency'] ?? 'USD',
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
            ]);

            $this->syncItems($invoice, $data['items'] ?? []);
            $invoice->recalculateTotals();
            $invoice->save();

            return $invoice;
        });

        // Optional: spin up a linked project when the form asked for one (the user
        // can pick an existing project instead, or leave it unlinked — not required).
        if (! $invoice->crm_project_id && $request->boolean('create_project')) {
            app(ProjectCreationService::class)->createFromInvoice($invoice, $user, automatic: false);
        }

        return redirect()->route('crm.invoices.show', $invoice)
            ->with('success', ($invoice->isEstimate() ? 'Estimate' : 'Invoice')." {$invoice->number} created.");
    }

    /** Manually create a managed project from this invoice and link it. */
    public function createProject(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);

        if ($invoice->crm_project_id) {
            return back()->with('error', 'This invoice is already linked to a project.');
        }

        $project = app(ProjectCreationService::class)->createFromInvoice($invoice, $request->user(), automatic: false);

        return redirect()->route('projects.show', $project)
            ->with('success', "Project {$project->project_number} created from invoice {$invoice->number}.");
    }

    public function show(Request $request, Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);
        $invoice->load(['company:id,name,email,phone', 'project:id,name', 'owner:id,name', 'items', 'payments.recorder:id,name']);

        return Inertia::render('Crm/Invoices/Show', [
            'invoice' => $this->shapeInvoice($invoice),
            'statuses' => $this->statusOptions(),
            'can' => ['manage' => $request->user()->can('manage invoices')],
        ]);
    }

    public function edit(Request $request, Invoice $invoice): Response
    {
        $this->authorize('update', $invoice);
        $invoice->load('items');

        return Inertia::render('Crm/Invoices/Form', $this->formProps($request) + [
            'invoice' => $this->shapeInvoice($invoice),
            'kind' => $invoice->kind,
        ]);
    }

    public function update(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);
        $data = $this->validateInvoice($request);

        DB::transaction(function () use ($invoice, $data) {
            $invoice->update([
                'company_id' => $data['company_id'] ?? null,
                'crm_project_id' => $data['crm_project_id'] ?? null,
                'kind' => $data['kind'],
                'status' => $data['status'] ?? $invoice->status->value,
                'issue_date' => $data['issue_date'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'tax_rate' => $data['tax_rate'] ?? 0,
                'discount_amount' => $data['discount_amount'] ?? 0,
                'currency' => $data['currency'] ?? 'USD',
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
            ]);

            $invoice->items()->delete();
            $this->syncItems($invoice, $data['items'] ?? []);
            $invoice->recalculateTotals();
            $invoice->save();
        });

        return redirect()->route('crm.invoices.show', $invoice)->with('success', "{$invoice->number} updated.");
    }

    public function destroy(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('delete', $invoice);
        $number = $invoice->number;
        $invoice->delete();

        return redirect()->route('crm.invoices.index')->with('success', "{$number} deleted.");
    }

    public function updateStatus(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);
        $validated = $request->validate(['status' => ['required', new Enum(InvoiceStatus::class)]]);
        $invoice->update(['status' => $validated['status']]);

        return back()->with('success', 'Status updated to '.$invoice->status->label().'.');
    }

    public function storePayment(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);
        $user = $request->user();

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:9999999999999',
            'paid_at' => 'required|date',
            'method' => 'nullable|in:card,check,wire,cash,other',
            'reference' => 'nullable|string|max:120',
            'notes' => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($invoice, $user, $validated) {
            $invoice->payments()->create([
                'organization_id' => $user->organization_id,
                'created_by' => $user->id,
                'amount' => $validated['amount'],
                'paid_at' => $validated['paid_at'],
                'method' => $validated['method'] ?? null,
                'reference' => $validated['reference'] ?? null,
                'status' => 'completed',
                'notes' => $validated['notes'] ?? null,
            ]);

            $invoice->syncPaymentState();
            $invoice->save();
        });

        return back()->with('success', 'Payment recorded.');
    }

    public function destroyPayment(Request $request, Invoice $invoice, Payment $payment): RedirectResponse
    {
        $this->authorize('update', $invoice);
        abort_unless($payment->crm_invoice_id === $invoice->id, 404);

        DB::transaction(function () use ($invoice, $payment) {
            $payment->delete();
            $invoice->syncPaymentState();
            $invoice->save();
        });

        return back()->with('success', 'Payment removed.');
    }

    /** Sequential per org + kind + year: INV-2026-0001 / EST-2026-0001. */
    private function generateNumber(int $orgId, string $kind): string
    {
        $prefix = $kind === 'estimate' ? 'EST' : 'INV';
        $year = now()->year;

        $last = Invoice::withTrashed()
            ->where('organization_id', $orgId)
            ->where('kind', $kind)
            ->where('number', 'like', "{$prefix}-{$year}-%")
            ->lockForUpdate()
            ->orderByDesc('number')
            ->value('number');

        $seq = $last ? ((int) substr($last, (int) strrpos($last, '-') + 1)) + 1 : 1;

        return sprintf('%s-%d-%04d', $prefix, $year, $seq);
    }

    /** @param array<int,array<string,mixed>> $items */
    private function syncItems(Invoice $invoice, array $items): void
    {
        foreach (array_values($items) as $i => $item) {
            $qty = (float) ($item['quantity'] ?? 1);
            $unit = (float) ($item['unit_price'] ?? 0);
            $invoice->items()->create([
                'description' => $item['description'],
                'quantity' => $qty,
                'unit_price' => $unit,
                'amount' => round($qty * $unit, 2),
                'position' => $i,
            ]);
        }
    }

    private function shapeInvoice(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'kind' => $invoice->kind,
            'status' => $invoice->status->value,
            'status_label' => $invoice->status->label(),
            'status_color' => $invoice->status->color(),
            'company_id' => $invoice->company_id,
            'company' => $invoice->relationLoaded('company') ? $invoice->company?->only(['id', 'name', 'email', 'phone']) : null,
            'crm_project_id' => $invoice->crm_project_id,
            'project' => $invoice->relationLoaded('project') ? $invoice->project?->only(['id', 'name']) : null,
            'issue_date' => $invoice->issue_date?->toDateString(),
            'due_date' => $invoice->due_date?->toDateString(),
            'subtotal' => (float) $invoice->subtotal,
            'tax_rate' => (float) $invoice->tax_rate,
            'tax_amount' => (float) $invoice->tax_amount,
            'discount_amount' => (float) $invoice->discount_amount,
            'total' => (float) $invoice->total,
            'amount_paid' => (float) $invoice->amount_paid,
            'balance' => (float) $invoice->balance,
            'currency' => $invoice->currency,
            'notes' => $invoice->notes,
            'terms' => $invoice->terms,
            'owner' => $invoice->relationLoaded('owner') ? $invoice->owner?->name : null,
            'items' => $invoice->relationLoaded('items') ? $invoice->items->map(fn ($it) => [
                'id' => $it->id,
                'description' => $it->description,
                'quantity' => (float) $it->quantity,
                'unit_price' => (float) $it->unit_price,
                'amount' => (float) $it->amount,
            ])->values() : [],
            'payments' => $invoice->relationLoaded('payments') ? $invoice->payments->map(fn ($p) => [
                'id' => $p->id,
                'amount' => (float) $p->amount,
                'paid_at' => $p->paid_at?->toDateString(),
                'method' => $p->method,
                'reference' => $p->reference,
                'notes' => $p->notes,
                'recorder' => $p->recorder?->name,
            ])->values() : [],
        ];
    }

    /** @return array<string,mixed> */
    private function formProps(Request $request): array
    {
        $orgId = $request->user()->organization_id;

        return [
            'companies' => Company::where('organization_id', $orgId)->orderBy('name')->get(['id', 'name']),
            'projects' => Project::where('organization_id', $orgId)->orderBy('name')->get(['id', 'name']),
            'statuses' => $this->statusOptions(),
        ];
    }

    private function statusOptions(): array
    {
        return collect(InvoiceStatus::cases())
            ->map(fn ($s) => ['value' => $s->value, 'label' => $s->label(), 'color' => $s->color()])
            ->all();
    }

    /** @return array<string,mixed> */
    private function validateInvoice(Request $request): array
    {
        $user = $request->user();

        return $request->validate([
            'kind' => 'required|in:invoice,estimate',
            'status' => ['nullable', new Enum(InvoiceStatus::class)],
            'company_id' => ['nullable', Rule::exists('companies', 'id')->where('organization_id', $user->organization_id)],
            'crm_project_id' => ['nullable', Rule::exists('crm_projects', 'id')->where('organization_id', $user->organization_id)],
            'create_project' => ['nullable', 'boolean'],
            'issue_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0|max:9999999999999',
            'currency' => 'nullable|string|size:3',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'items' => 'array',
            'items.*.description' => 'required|string|max:500',
            'items.*.quantity' => 'required|numeric|min:0|max:9999999999',
            'items.*.unit_price' => 'required|numeric|min:0|max:9999999999999',
        ]);
    }
}
