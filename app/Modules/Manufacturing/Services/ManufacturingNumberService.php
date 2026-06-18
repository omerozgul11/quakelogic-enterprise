<?php

namespace App\Modules\Manufacturing\Services;

use App\Modules\Manufacturing\Models\WorkOrder;
use Illuminate\Support\Facades\DB;

/**
 * Sequential work-order numbers, WO-YYYY-NNNN, generated under a row lock.
 * Mirrors ProposalNumberService / ProcurementNumberService.
 */
class ManufacturingNumberService
{
    public function generate(int $organizationId): string
    {
        return DB::transaction(function () use ($organizationId) {
            $year = now()->year;

            $count = WorkOrder::withTrashed()
                ->where('organization_id', $organizationId)
                ->whereYear('created_at', $year)
                ->lockForUpdate()
                ->count();

            return sprintf('WO-%d-%04d', $year, $count + 1);
        });
    }
}
