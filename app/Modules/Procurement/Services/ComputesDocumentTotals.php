<?php

namespace App\Modules\Procurement\Services;

use Illuminate\Database\Eloquent\Collection;

/**
 * Shared line-total maths for purchase requests, quotations and bills: each
 * line's total is quantity × unit cost (pre-tax); the document subtotal and tax
 * are the sums across lines, with tax taken per line from its own rate.
 */
trait ComputesDocumentTotals
{
    /**
     * Persist each line's `line_total` and return the document rollup.
     *
     * @param  Collection<int,\Illuminate\Database\Eloquent\Model>  $items
     * @return array{subtotal:float,tax:float}
     */
    protected function rollupLines(Collection $items): array
    {
        $subtotal = 0.0;
        $tax = 0.0;

        foreach ($items as $item) {
            $lineSubtotal = round((float) $item->quantity * (float) $item->unit_cost, 2);
            if ((float) $item->line_total !== $lineSubtotal) {
                $item->forceFill(['line_total' => $lineSubtotal])->save();
            }
            $subtotal += $lineSubtotal;
            $tax += round($lineSubtotal * (float) $item->tax_rate / 100, 2);
        }

        return ['subtotal' => round($subtotal, 2), 'tax' => round($tax, 2)];
    }
}
