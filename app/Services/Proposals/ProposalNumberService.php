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
            $prefix = "QL-{$year}-";
            // Derive the next sequence from the highest existing number (soft-deleted
            // included — the unique index covers them, so a number must never be
            // reused). Max-based, not count-based: counting collides whenever the
            // numbers aren't contiguous (e.g. after a recovery/bulk import).
            $max = ProposalSubmission::withTrashed()
                ->where('organization_id', $organizationId)
                ->where('proposal_number', 'like', $prefix . '%')
                ->lockForUpdate()
                ->pluck('proposal_number')
                ->map(fn (string $number) => (int) substr($number, -4))
                ->max();

            $sequence = str_pad((int) ($max ?? 0) + 1, 4, '0', STR_PAD_LEFT);
            return "{$prefix}{$sequence}";
        });
    }
}
