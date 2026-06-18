<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\CreditNote;
use Illuminate\Support\Facades\DB;

/**
 * Sequential credit-note numbers, CN-YYYY-NNNN, generated under a row lock.
 * Mirrors the other Hub number services.
 */
class FinanceNumberService
{
    public function generateCreditNote(int $organizationId): string
    {
        return DB::transaction(function () use ($organizationId) {
            $year = now()->year;

            $count = CreditNote::withTrashed()
                ->where('organization_id', $organizationId)
                ->whereYear('created_at', $year)
                ->lockForUpdate()
                ->count();

            return sprintf('CN-%d-%04d', $year, $count + 1);
        });
    }
}
