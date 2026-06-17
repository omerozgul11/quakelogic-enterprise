<?php

namespace App\Services\Proposals;

use App\Models\ProposalSubmission;
use Illuminate\Support\Facades\DB;

class ProposalNumberService
{
    public function generate(int $organizationId): string
    {
        return DB::transaction(function () use ($organizationId) {
            $year = now()->year;
            // Count soft-deleted proposals too — the proposal_number unique index
            // still includes them, so the sequence must never reuse a number.
            $count = ProposalSubmission::withTrashed()
                ->where('organization_id', $organizationId)
                ->whereYear('created_at', $year)
                ->lockForUpdate()
                ->count();

            $sequence = str_pad($count + 1, 4, '0', STR_PAD_LEFT);
            return "QL-{$year}-{$sequence}";
        });
    }
}
