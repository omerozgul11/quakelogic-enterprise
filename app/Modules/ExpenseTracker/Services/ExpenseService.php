<?php

namespace App\Modules\ExpenseTracker\Services;

use App\Modules\ExpenseTracker\Enums\ExpenseStatus;
use App\Modules\ExpenseTracker\Models\Expense;
use RuntimeException;

/**
 * Owns the expense approval lifecycle:
 *   draft → submitted → approved / rejected → reimbursed / paid.
 * Crossing into an approved (spend) state triggers the budget alert check.
 */
class ExpenseService
{
    public function __construct(private readonly BudgetAlertService $budgets) {}

    public function submit(Expense $expense): Expense
    {
        $expense->forceFill([
            'status' => ExpenseStatus::Submitted,
            'submitted_at' => now(),
            'reject_reason' => null,
        ])->save();

        return $expense;
    }

    public function approve(Expense $expense, int $approvedBy): Expense
    {
        $expense->forceFill([
            'status' => ExpenseStatus::Approved,
            'approved_by' => $approvedBy,
            'approved_at' => now(),
            'reject_reason' => null,
        ])->save();

        $this->budgets->checkAfterApproval($expense);

        return $expense;
    }

    public function reject(Expense $expense, int $approvedBy, string $reason): Expense
    {
        $expense->forceFill([
            'status' => ExpenseStatus::Rejected,
            'approved_by' => $approvedBy,
            'approved_at' => now(),
            'reject_reason' => $reason,
        ])->save();

        return $expense;
    }

    public function reimburse(Expense $expense): Expense
    {
        if ($expense->status !== ExpenseStatus::Approved) {
            throw new RuntimeException('Only an approved expense can be marked reimbursed.');
        }

        $expense->forceFill([
            'status' => ExpenseStatus::Reimbursed,
            'reimbursed_at' => now(),
        ])->save();

        return $expense;
    }

    public function markPaid(Expense $expense): Expense
    {
        $expense->forceFill(['status' => ExpenseStatus::Paid])->save();

        return $expense;
    }
}
