<?php

namespace App\Modules\ExpenseTracker\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Crm\Project;
use App\Models\ProposalSubmission;
use App\Modules\ExpenseTracker\Enums\ExpenseStatus;
use App\Modules\ExpenseTracker\Enums\PaymentMethod;
use App\Modules\ExpenseTracker\Http\Requests\ExpenseRequest;
use App\Modules\ExpenseTracker\Models\Expense;
use App\Modules\ExpenseTracker\Models\ExpenseAttachment;
use App\Modules\ExpenseTracker\Models\ExpenseCategory;
use App\Modules\ExpenseTracker\Services\ExpenseNumberService;
use App\Modules\ExpenseTracker\Services\ExpenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function __construct(
        private readonly ExpenseService $service,
        private readonly ExpenseNumberService $numbers,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Expense::class);
        $orgId = $request->user()->organization_id;

        $expenses = Expense::where('organization_id', $orgId)
            ->with(['category:id,name,color', 'owner:id,name'])
            ->withCount('attachments')
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('vendor', 'like', "%{$s}%")
                ->orWhere('number', 'like', "%{$s}%")
                ->orWhere('description', 'like', "%{$s}%")))
            ->when($request->status, fn ($q, $st) => $q->where('status', $st))
            ->when($request->category, fn ($q, $c) => $q->where('expense_category_id', $c))
            ->when($request->from, fn ($q, $d) => $q->whereDate('expense_date', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->whereDate('expense_date', '<=', $d))
            ->orderByDesc('expense_date')->orderByDesc('id')
            ->paginate(20)->withQueryString()
            ->through(fn (Expense $e) => $this->row($e));

        return Inertia::render('Expenses/Expenses/Index', [
            'expenses' => $expenses,
            'filters' => $request->only(['search', 'status', 'category', 'from', 'to']),
            'statuses' => ExpenseStatus::options(),
            'formOptions' => $this->formOptions($orgId),
            'can' => ['manage' => $request->user()->can('manage expenses')],
        ]);
    }

    public function store(ExpenseRequest $request): RedirectResponse
    {
        $this->authorize('create', Expense::class);
        $user = $request->user();

        $expense = Expense::create([
            ...$request->validated(),
            'organization_id' => $user->organization_id,
            'created_by' => $user->id,
            'owner_id' => $user->id,
            'number' => $this->numbers->generate($user->organization_id),
            'status' => ExpenseStatus::Draft->value,
        ]);

        return redirect()->route('expenses.show', $expense)->with('success', "Expense {$expense->number} created.");
    }

    public function show(Request $request, Expense $expense): Response
    {
        $this->authorize('view', $expense);
        $orgId = $request->user()->organization_id;

        $expense->load([
            'category:id,name,color', 'owner:id,name', 'approver:id,name',
            'company:id,name', 'project:id,name', 'proposal:id,project_name,proposal_number',
            'attachments' => fn ($q) => $q->latest('id'),
            'attachments.uploader:id,name',
        ]);

        return Inertia::render('Expenses/Expenses/Show', [
            'expense' => [
                ...$this->row($expense),
                'notes' => $expense->notes,
                'reject_reason' => $expense->reject_reason,
                'submitted_at' => $expense->submitted_at?->toIso8601String(),
                'approved_at' => $expense->approved_at?->toIso8601String(),
                'reimbursed_at' => $expense->reimbursed_at?->toIso8601String(),
                'approver' => $expense->approver?->name,
                'company' => $expense->company?->name,
                'project' => $expense->project?->name,
                'proposal' => $expense->proposal
                    ? ($expense->proposal->project_name ?: $expense->proposal->proposal_number)
                    : null,
                'attachments' => $expense->attachments->map(fn (ExpenseAttachment $a) => [
                    'id' => $a->id,
                    'display_name' => $a->display_name,
                    'size' => $a->size,
                    'mime_type' => $a->mime_type,
                    'uploaded_by' => $a->uploader?->name,
                    'created_at' => $a->created_at?->toIso8601String(),
                ]),
            ],
            'formOptions' => $this->formOptions($orgId),
            'statuses' => ExpenseStatus::options(),
            'can' => [
                'manage' => $request->user()->can('manage expenses'),
                'update' => $request->user()->can('update', $expense),
                'submit' => $request->user()->can('submit', $expense),
                'approve' => $request->user()->can('approve', $expense),
                'reimburse' => $request->user()->can('reimburse', $expense),
                'delete' => $request->user()->can('delete', $expense),
            ],
        ]);
    }

    public function update(ExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $this->authorize('update', $expense);
        $expense->update($request->validated());

        return back()->with('success', 'Expense updated.');
    }

    public function destroy(Request $request, Expense $expense): RedirectResponse
    {
        $this->authorize('delete', $expense);
        $number = $expense->number;
        $expense->delete();

        return redirect()->route('expenses.index')->with('success', "Expense {$number} deleted.");
    }

    // ── Lifecycle ───────────────────────────────────────────────────────────

    public function submit(Request $request, Expense $expense): RedirectResponse
    {
        $this->authorize('submit', $expense);
        $this->service->submit($expense);

        return back()->with('success', 'Expense submitted for approval.');
    }

    public function approve(Request $request, Expense $expense): RedirectResponse
    {
        $this->authorize('approve', $expense);
        $this->service->approve($expense, $request->user()->id);

        return back()->with('success', 'Expense approved.');
    }

    public function reject(Request $request, Expense $expense): RedirectResponse
    {
        $this->authorize('reject', $expense);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $this->service->reject($expense, $request->user()->id, $data['reason']);

        return back()->with('success', 'Expense rejected.');
    }

    public function reimburse(Request $request, Expense $expense): RedirectResponse
    {
        $this->authorize('reimburse', $expense);
        $this->service->reimburse($expense);

        return back()->with('success', 'Expense marked reimbursed.');
    }

    // ── Receipts ────────────────────────────────────────────────────────────

    public function storeReceipt(Request $request, Expense $expense): RedirectResponse
    {
        $this->authorize('update', $expense);
        $request->validate([
            'file' => ['required', 'file', 'max:25600', 'mimetypes:application/pdf,image/jpeg,image/png,image/heic,image/heif'],
        ]);

        $file = $request->file('file');
        $stored = (string) Str::ulid().'.'.($file->getClientOriginalExtension() ?: 'bin');
        $path = $file->storeAs("expense-receipts/{$expense->id}", $stored, 'local');

        $expense->attachments()->create([
            'organization_id' => $expense->organization_id,
            'uploaded_by' => $request->user()->id,
            'display_name' => $file->getClientOriginalName(),
            'original_filename' => $file->getClientOriginalName(),
            'stored_filename' => $stored,
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()),
        ]);

        return back()->with('success', 'Receipt uploaded.');
    }

    public function downloadReceipt(Request $request, Expense $expense, ExpenseAttachment $attachment): mixed
    {
        $this->authorize('view', $expense);
        abort_unless($attachment->expense_id === $expense->id, 404);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->display_name);
    }

    public function destroyReceipt(Request $request, Expense $expense, ExpenseAttachment $attachment): RedirectResponse
    {
        $this->authorize('update', $expense);
        abort_unless($attachment->expense_id === $expense->id, 404);

        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();

        return back()->with('success', 'Receipt removed.');
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /** @return array<string,mixed> */
    private function row(Expense $e): array
    {
        return [
            'id' => $e->id,
            'number' => $e->number,
            'vendor' => $e->vendor,
            'description' => $e->description,
            'amount' => (float) $e->amount,
            'currency' => $e->currency,
            'payment_method' => $e->payment_method?->value,
            'payment_method_label' => $e->payment_method?->label(),
            'status' => $e->status->value,
            'status_label' => $e->status->label(),
            'status_color' => $e->status->color(),
            'source' => $e->source,
            'is_billable' => $e->is_billable,
            'expense_date' => $e->expense_date?->toDateString(),
            'category' => $e->category?->name,
            'category_id' => $e->expense_category_id,
            'company_id' => $e->company_id,
            'crm_project_id' => $e->crm_project_id,
            'proposal_id' => $e->proposal_id,
            'owner' => $e->owner?->name,
            'attachments_count' => $e->attachments_count ?? $e->attachments()->count(),
        ];
    }

    /** Reference lists for the create/edit form. @return array<string,mixed> */
    private function formOptions(int $orgId): array
    {
        return [
            'categories' => ExpenseCategory::where('organization_id', $orgId)->where('is_active', true)
                ->orderBy('name')->get(['id', 'name'])
                ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])->all(),
            'companies' => Company::where('organization_id', $orgId)->orderBy('name')->limit(500)
                ->get(['id', 'name'])->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])->all(),
            'projects' => Project::where('organization_id', $orgId)->orderByDesc('id')->limit(500)
                ->get(['id', 'name'])->map(fn ($p) => ['value' => $p->id, 'label' => $p->name])->all(),
            'proposals' => ProposalSubmission::where('organization_id', $orgId)->orderByDesc('id')->limit(500)
                ->get(['id', 'project_name', 'proposal_number'])
                ->map(fn ($p) => ['value' => $p->id, 'label' => $p->project_name ?: $p->proposal_number])->all(),
            'paymentMethods' => PaymentMethod::options(),
        ];
    }
}
