<?php

namespace App\Modules\ExpenseTracker\Services;

use App\Modules\ExpenseTracker\Enums\ExpenseStatus;
use App\Modules\ExpenseTracker\Models\Expense;
use App\Modules\ExpenseTracker\Models\RecurringExpense;
use Illuminate\Support\Carbon;

/**
 * Turns recurring cost schedules into concrete expense rows. The daily command
 * calls generateDue() (catch-up safe across missed runs); the "Generate now"
 * button calls generateOnce().
 */
class RecurringExpenseGenerator
{
    public function __construct(
        private readonly ExpenseNumberService $numbers,
        private readonly BudgetAlertService $budgets,
    ) {}

    /**
     * Create an expense for every period due up to today and advance the
     * schedule. Returns the number of expenses created.
     */
    public function generateDue(RecurringExpense $recurring, ?Carbon $asOf = null): int
    {
        $asOf = $asOf ? $asOf->copy()->startOfDay() : now()->startOfDay();
        $created = 0;

        if (! $recurring->is_active) {
            return 0;
        }

        // Catch up one period at a time so a cron outage never skips a charge.
        while (
            $recurring->next_run_date->lte($asOf)
            && ($recurring->end_date === null || $recurring->next_run_date->lte($recurring->end_date))
        ) {
            $this->createExpense($recurring, $recurring->next_run_date->copy());
            $recurring->forceFill([
                'next_run_date' => $recurring->frequency->advance($recurring->next_run_date, $recurring->interval_count),
                'last_generated_at' => now(),
            ])->save();
            $created++;
        }

        return $created;
    }

    /** Generate a single expense immediately (manual run) and advance the schedule once. */
    public function generateOnce(RecurringExpense $recurring): Expense
    {
        $expense = $this->createExpense($recurring, now());

        $recurring->forceFill([
            'next_run_date' => $recurring->frequency->advance(
                $recurring->next_run_date->isFuture() ? $recurring->next_run_date : now(),
                $recurring->interval_count,
            ),
            'last_generated_at' => now(),
        ])->save();

        return $expense;
    }

    private function createExpense(RecurringExpense $r, Carbon $date): Expense
    {
        $approved = $r->auto_approve;

        $expense = Expense::create([
            'organization_id' => $r->organization_id,
            'created_by' => $r->created_by,
            'owner_id' => $r->owner_id,
            'expense_category_id' => $r->expense_category_id,
            'recurring_expense_id' => $r->id,
            'company_id' => $r->company_id,
            'crm_project_id' => $r->crm_project_id,
            'proposal_id' => $r->proposal_id,
            'number' => $this->numbers->generate($r->organization_id),
            'vendor' => $r->vendor,
            'description' => $r->name,
            'amount' => $r->amount,
            'currency' => $r->currency,
            'payment_method' => $r->payment_method?->value,
            'is_billable' => $r->is_billable,
            'status' => $approved ? ExpenseStatus::Approved->value : ExpenseStatus::Draft->value,
            'expense_date' => $date->toDateString(),
            'approved_at' => $approved ? now() : null,
        ]);

        if ($approved) {
            $this->budgets->checkAfterApproval($expense);
        }

        return $expense;
    }
}
