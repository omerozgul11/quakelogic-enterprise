<?php

namespace App\Modules\ExpenseTracker\Services;

use App\Models\User;
use App\Modules\ExpenseTracker\Models\Expense;
use App\Modules\ExpenseTracker\Models\ExpenseCategory;
use App\Notifications\ActivityNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Fires an in-app notification the moment an approved expense pushes its
 * category's period spend past the configured budget. Only the crossing
 * approval alerts (spend-before <= budget < spend-after), so managers are not
 * re-notified for every subsequent expense in the same period.
 */
class BudgetAlertService
{
    /** Called after an expense becomes "spend" (approved). Alerts on the budget crossing. */
    public function checkAfterApproval(Expense $expense): void
    {
        $category = $expense->expense_category_id
            ? ExpenseCategory::find($expense->expense_category_id)
            : null;

        if (! $category || $category->budget_amount === null) {
            return;
        }

        $budget = (float) $category->budget_amount;
        $spentAfter = $category->spentThisPeriod($expense->expense_date);
        $spentBefore = $spentAfter - (float) $expense->amount;

        // Only the approval that first crosses the threshold raises the alert.
        if ($spentBefore > $budget || $spentAfter <= $budget) {
            return;
        }

        $this->notify($category, $expense, $budget, $spentAfter);
    }

    private function notify(ExpenseCategory $category, Expense $expense, float $budget, float $spent): void
    {
        $period = $category->budget_period === 'yearly' ? 'year'
            : ($category->budget_period === 'quarterly' ? 'quarter' : 'month');

        $recipients = User::query()
            ->where('organization_id', $category->organization_id)
            ->permission('manage expenses')
            ->get();

        // Include the person who incurred the expense, even if they are not a manager.
        if (! $recipients->contains('id', $expense->owner_id)) {
            if ($owner = User::find($expense->owner_id)) {
                $recipients->push($owner);
            }
        }

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new ActivityNotification([
            'type' => 'budget',
            'title' => "Over budget: {$category->name}",
            'message' => sprintf(
                'Spent %s of %s %s this %s.',
                number_format($spent),
                number_format($budget),
                $category->currency,
                $period,
            ),
            'url' => '/expenses/categories',
            'icon' => 'receipt',
        ]));
    }
}
