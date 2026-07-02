<?php

namespace App\Modules\ExpenseTracker\Services;

use App\Modules\ExpenseTracker\Enums\ExpenseStatus;
use App\Modules\ExpenseTracker\Models\Expense;
use App\Modules\ExpenseTracker\Models\ExpensePayment;
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

    /**
     * Record a (possibly partial) payment against an expense and re-derive its
     * paid totals. Payment status (Due / Partially paid / Paid) then follows
     * from amount vs amount_paid — see Expense::paymentStatus().
     *
     * @param  array{amount:float|string,currency?:string,paid_on?:string,method?:string|null,reference?:string|null,note?:string|null}  $data
     */
    public function recordPayment(Expense $expense, array $data, int $userId): ExpensePayment
    {
        $payment = $expense->payments()->create([
            'organization_id' => $expense->organization_id,
            'created_by' => $userId,
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? $expense->currency,
            'paid_on' => $data['paid_on'] ?? now()->toDateString(),
            'method' => $data['method'] ?? null,
            'reference' => $data['reference'] ?? null,
            'note' => $data['note'] ?? null,
        ]);

        $this->syncPaymentTotals($expense);

        return $payment;
    }

    public function removePayment(Expense $expense, ExpensePayment $payment): void
    {
        $payment->delete();
        $this->syncPaymentTotals($expense);
    }

    /** Recompute amount_paid + paid_at from the live (non-deleted) payments. */
    public function syncPaymentTotals(Expense $expense): Expense
    {
        $paid = (float) $expense->payments()->sum('amount');
        $fullyPaid = (float) $expense->amount > 0 && $paid + 0.005 >= (float) $expense->amount;

        $expense->forceFill([
            'amount_paid' => round($paid, 2),
            'paid_at' => $fullyPaid ? ($expense->paid_at ?? now()) : null,
        ])->save();

        return $expense;
    }
}
