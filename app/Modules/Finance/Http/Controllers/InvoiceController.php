<?php

namespace App\Modules\Finance\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Crm\Invoice;
use App\Models\Crm\Payment;
use App\Modules\Finance\Models\PaymentIntent;
use App\Modules\Finance\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class InvoiceController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function index(Request $request): Response
    {
        $this->can($request, 'view finance');
        $orgId = $request->user()->organization_id;

        $invoices = Invoice::where('organization_id', $orgId)->where('kind', 'invoice')
            ->with('company:id,name')
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('number', 'like', "%{$s}%")
                ->orWhereHas('company', fn ($c) => $c->where('name', 'like', "%{$s}%"))))
            ->when($request->status, fn ($q, $st) => $q->where('status', $st))
            ->when($request->due === 'overdue', fn ($q) => $q->whereNotNull('due_date')->whereDate('due_date', '<', now()->toDateString())
                ->whereNotIn('status', [InvoiceStatus::Paid->value, InvoiceStatus::Void->value]))
            ->when($request->due === 'unpaid', fn ($q) => $q->whereNotIn('status', [InvoiceStatus::Paid->value, InvoiceStatus::Void->value, InvoiceStatus::Draft->value]))
            ->latest('issue_date')->latest('id')
            ->paginate(20)->withQueryString()
            ->through(fn (Invoice $i) => [
                'id' => $i->id,
                'number' => $i->number,
                'company' => $i->company?->name,
                'total' => (float) $i->total,
                'amount_paid' => (float) $i->amount_paid,
                'balance' => round((float) $i->total - (float) $i->amount_paid, 2),
                'currency' => $i->currency,
                'status' => $i->status->value,
                'status_label' => $i->status->label(),
                'status_color' => $i->status->color(),
                'issue_date' => $i->issue_date?->toDateString(),
                'due_date' => $i->due_date?->toDateString(),
                'overdue' => $i->due_date && $i->due_date->isPast() && ! in_array($i->status, [InvoiceStatus::Paid, InvoiceStatus::Void], true),
            ]);

        return Inertia::render('Finance/Invoices/Index', [
            'invoices' => $invoices,
            'filters' => $request->only(['search', 'status', 'due']),
            'statuses' => collect(InvoiceStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
            'can' => ['pay' => $request->user()->can('process payments')],
        ]);
    }

    public function show(Request $request, Invoice $invoice): Response
    {
        $this->can($request, 'view finance');
        $this->sameOrg($request, $invoice);

        $invoice->load(['company:id,name,email', 'items', 'payments' => fn ($q) => $q->orderByDesc('id')]);
        $intents = PaymentIntent::where('crm_invoice_id', $invoice->id)->latest('id')->get();

        return Inertia::render('Finance/Invoices/Show', [
            'invoice' => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'status' => $invoice->status->value,
                'status_label' => $invoice->status->label(),
                'status_color' => $invoice->status->color(),
                'company' => $invoice->company?->name,
                'company_email' => $invoice->company?->email,
                'issue_date' => $invoice->issue_date?->toDateString(),
                'due_date' => $invoice->due_date?->toDateString(),
                'currency' => $invoice->currency,
                'subtotal' => (float) $invoice->subtotal,
                'tax_amount' => (float) $invoice->tax_amount,
                'discount_amount' => (float) $invoice->discount_amount,
                'total' => (float) $invoice->total,
                'amount_paid' => (float) $invoice->amount_paid,
                'balance' => round((float) $invoice->total - (float) $invoice->amount_paid, 2),
                'notes' => $invoice->notes,
                'terms' => $invoice->terms,
                'items' => $invoice->items->map(fn ($it) => [
                    'id' => $it->id, 'description' => $it->description ?? $it->name ?? '—',
                    'quantity' => (float) ($it->quantity ?? 1), 'unit_price' => (float) ($it->unit_price ?? 0),
                    'amount' => (float) ($it->amount ?? 0),
                ]),
                'payments' => $invoice->payments->map(fn (Payment $p) => [
                    'id' => $p->id, 'amount' => (float) $p->amount, 'method' => $p->method,
                    'reference' => $p->reference, 'status' => $p->status, 'paid_at' => $p->paid_at?->toDateString(),
                ]),
            ],
            'intents' => $intents->map(fn (PaymentIntent $i) => [
                'id' => $i->id, 'provider' => $i->provider, 'amount' => (float) $i->amount,
                'status' => $i->status->value, 'status_label' => $i->status->label(), 'status_color' => $i->status->color(),
                'checkout_url' => $i->checkout_url, 'reference' => $i->reference,
            ]),
            'provider' => config('finance.provider', 'fake'),
            'can' => ['pay' => $request->user()->can('process payments')],
        ]);
    }

    public function collect(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->can($request, 'process payments');
        $this->sameOrg($request, $invoice);

        $balance = round((float) $invoice->total - (float) $invoice->amount_paid, 2);
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:'.max(0.01, $balance)],
        ]);

        try {
            $intent = $this->payments->createCheckout($invoice, (float) $validated['amount'], $request->user()->id);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Payment link created via {$intent->provider}.");
    }

    public function capture(Request $request, Invoice $invoice, PaymentIntent $intent): RedirectResponse
    {
        $this->can($request, 'process payments');
        $this->sameOrg($request, $invoice);
        abort_unless($intent->crm_invoice_id === $invoice->id, 404);

        $this->payments->capture($intent, $request->user()->id);

        return back()->with('success', 'Payment captured and applied to the invoice.');
    }

    public function recordPayment(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->can($request, 'process payments');
        $this->sameOrg($request, $invoice);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', 'string', 'max:60'],
            'reference' => ['nullable', 'string', 'max:120'],
            'paid_at' => ['nullable', 'date'],
        ]);

        $invoice->payments()->create([
            'organization_id' => $invoice->organization_id,
            'created_by' => $request->user()->id,
            'amount' => $validated['amount'],
            'method' => $validated['method'],
            'reference' => $validated['reference'] ?? null,
            'paid_at' => $validated['paid_at'] ?? now()->toDateString(),
            'status' => 'completed',
        ]);
        $invoice->syncPaymentState();
        $invoice->save();

        return back()->with('success', 'Payment recorded.');
    }

    private function can(Request $request, string $permission): void
    {
        abort_unless($request->user()->can($permission), 403);
    }

    private function sameOrg(Request $request, Invoice $invoice): void
    {
        if ($invoice->organization_id !== $request->user()->organization_id) {
            abort(403);
        }
    }
}
