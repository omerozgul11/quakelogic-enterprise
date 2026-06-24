<?php

namespace App\Modules\ExpenseTracker\Services;

use App\Modules\ExpenseTracker\Models\Expense;
use Illuminate\Support\Facades\DB;

/**
 * Sequential expense numbers, EXP-YYYY-NNNN, generated under a row lock.
 * Counts include soft-deleted rows so a number is never reused. Mirrors the
 * other Hub number services.
 */
class ExpenseNumberService
{
    public function generate(int $organizationId): string
    {
        return DB::transaction(function () use ($organizationId) {
            $year = now()->year;

            $count = Expense::withTrashed()
                ->where('organization_id', $organizationId)
                ->whereYear('created_at', $year)
                ->lockForUpdate()
                ->count();

            return sprintf('EXP-%d-%04d', $year, $count + 1);
        });
    }
}
