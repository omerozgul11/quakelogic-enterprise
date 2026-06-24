<?php

namespace App\Modules\ExpenseTracker\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ExpenseTracker\Enums\PaymentMethod;
use App\Modules\ExpenseTracker\Enums\RecurringFrequency;
use App\Modules\ExpenseTracker\Http\Requests\RecurringExpenseRequest;
use App\Modules\ExpenseTracker\Models\ExpenseCategory;
use App\Modules\ExpenseTracker\Models\RecurringExpense;
use App\Modules\ExpenseTracker\Services\RecurringExpenseGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecurringExpenseController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', RecurringExpense::class);
        $orgId = $request->user()->organization_id;

        $recurring = RecurringExpense::where('organization_id', $orgId)
            ->with(['category:id,name', 'owner:id,name'])
            ->withCount('expenses')
            ->orderByDesc('is_active')->orderBy('next_run_date')->get()
            ->map(fn (RecurringExpense $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'vendor' => $r->vendor,
                'amount' => (float) $r->amount,
                'currency' => $r->currency,
                'frequency' => $r->frequency->value,
                'frequency_label' => $r->frequency->label(),
                'frequency_color' => $r->frequency->color(),
                'interval_count' => $r->interval_count,
                'next_run_date' => $r->next_run_date?->toDateString(),
                'start_date' => $r->start_date?->toDateString(),
                'end_date' => $r->end_date?->toDateString(),
                'auto_approve' => $r->auto_approve,
                'is_active' => $r->is_active,
                'is_billable' => $r->is_billable,
                'payment_method' => $r->payment_method?->value,
                'category' => $r->category?->name,
                'category_id' => $r->expense_category_id,
                'owner' => $r->owner?->name,
                'expenses_count' => $r->expenses_count,
            ]);

        return Inertia::render('Expenses/Recurring/Index', [
            'recurring' => $recurring,
            'formOptions' => [
                'categories' => ExpenseCategory::where('organization_id', $orgId)->where('is_active', true)
                    ->orderBy('name')->get(['id', 'name'])
                    ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])->all(),
                'frequencies' => RecurringFrequency::options(),
                'paymentMethods' => PaymentMethod::options(),
            ],
            'can' => ['manage' => $request->user()->can('manage expenses')],
        ]);
    }

    public function store(RecurringExpenseRequest $request): RedirectResponse
    {
        $this->authorize('create', RecurringExpense::class);
        $user = $request->user();
        $data = $request->validated();

        RecurringExpense::create([
            ...$data,
            'organization_id' => $user->organization_id,
            'created_by' => $user->id,
            'owner_id' => $user->id,
            'next_run_date' => $data['start_date'],
        ]);

        return back()->with('success', 'Recurring cost created.');
    }

    public function update(RecurringExpenseRequest $request, RecurringExpense $recurring): RedirectResponse
    {
        $this->authorize('update', $recurring);
        $recurring->update($request->validated());

        return back()->with('success', 'Recurring cost updated.');
    }

    public function destroy(Request $request, RecurringExpense $recurring): RedirectResponse
    {
        $this->authorize('delete', $recurring);
        $name = $recurring->name;
        $recurring->delete();

        return back()->with('success', "Recurring cost \"{$name}\" removed.");
    }

    public function generateNow(Request $request, RecurringExpense $recurring, RecurringExpenseGenerator $generator): RedirectResponse
    {
        $this->authorize('update', $recurring);
        $expense = $generator->generateOnce($recurring);

        return redirect()->route('expenses.show', $expense)
            ->with('success', "Generated expense {$expense->number} from \"{$recurring->name}\".");
    }
}
