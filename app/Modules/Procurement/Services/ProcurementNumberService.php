<?php

namespace App\Modules\Procurement\Services;

use App\Modules\Procurement\Models\Bill;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\PurchaseRequest;
use App\Modules\Procurement\Models\Quotation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * Sequential procurement document numbers, PREFIX-YYYY-NNNN, generated under a
 * row lock so concurrent creates never collide. Mirrors ProposalNumberService.
 *   PO-YYYY-NNNN   purchase orders
 *   PR-YYYY-NNNN   purchase requests
 *   RFQ-YYYY-NNNN  quotations
 *   BILL-YYYY-NNNN bills
 */
class ProcurementNumberService
{
    public function generate(int $organizationId): string
    {
        return $this->generateFor($organizationId, 'PO', PurchaseOrder::class);
    }

    public function purchaseRequest(int $organizationId): string
    {
        return $this->generateFor($organizationId, 'PR', PurchaseRequest::class);
    }

    public function quotation(int $organizationId): string
    {
        return $this->generateFor($organizationId, 'RFQ', Quotation::class);
    }

    public function bill(int $organizationId): string
    {
        return $this->generateFor($organizationId, 'BILL', Bill::class);
    }

    /**
     * @param  class-string  $modelClass  The document model (its table backs the sequence).
     */
    public function generateFor(int $organizationId, string $prefix, string $modelClass): string
    {
        return DB::transaction(function () use ($organizationId, $prefix, $modelClass) {
            $year = now()->year;

            // Count trashed rows too — the (organization_id, number) unique index
            // includes soft-deleted rows, so the sequence must not reuse a value.
            $query = in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)
                ? $modelClass::withTrashed()
                : $modelClass::query();

            $count = $query
                ->where('organization_id', $organizationId)
                ->whereYear('created_at', $year)
                ->lockForUpdate()
                ->count();

            return sprintf('%s-%d-%04d', $prefix, $year, $count + 1);
        });
    }
}
