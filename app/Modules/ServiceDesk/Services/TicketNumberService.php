<?php

namespace App\Modules\ServiceDesk\Services;

use App\Modules\ServiceDesk\Models\Ticket;
use Illuminate\Support\Facades\DB;

/**
 * Sequential ticket numbers, TKT-YYYY-NNNN, generated under a row lock. Mirrors
 * the other Hub number services.
 */
class TicketNumberService
{
    public function generate(int $organizationId): string
    {
        return DB::transaction(function () use ($organizationId) {
            $year = now()->year;

            $count = Ticket::withTrashed()
                ->where('organization_id', $organizationId)
                ->whereYear('created_at', $year)
                ->lockForUpdate()
                ->count();

            return sprintf('TKT-%d-%04d', $year, $count + 1);
        });
    }
}
