<?php

namespace App\Modules\Procurement\Services;

use App\Modules\Procurement\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;

/**
 * Sequential purchase-order numbers, PO-YYYY-NNNN, generated under a row lock so
 * concurrent creates never collide. Mirrors ProposalNumberService.
 */
class ProcurementNumberService
{
    public function generate(int $organizationId): string
    {
        return DB::transaction(function () use ($organizationId) {
            $year = now()->year;

            // Count trashed POs too — the (organization_id, number) unique index
            // includes soft-deleted rows, so the sequence must not reuse a value.
            $count = PurchaseOrder::withTrashed()
                ->where('organization_id', $organizationId)
                ->whereYear('created_at', $year)
                ->lockForUpdate()
                ->count();

            return sprintf('PO-%d-%04d', $year, $count + 1);
        });
    }
}
