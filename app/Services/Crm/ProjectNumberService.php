<?php

namespace App\Services\Crm;

use App\Models\Crm\Project;
use Illuminate\Support\Facades\DB;

/**
 * Generates a unique, sequential project number per organization — e.g.
 * QL-PROJ-2026-001. Mirrors ProposalNumberService: wrapped in a transaction with
 * a row lock, soft-deleted rows included (a number is never reused), and the
 * next sequence derived from the MAX (not the count, which collides on gaps).
 */
class ProjectNumberService
{
    public function generate(int $organizationId, ?string $prefix = 'QL-PROJ'): string
    {
        $prefix = $prefix !== null && trim($prefix) !== '' ? rtrim(trim($prefix), '-') : 'QL-PROJ';

        return DB::transaction(function () use ($organizationId, $prefix) {
            $year = now()->year;
            $full = "{$prefix}-{$year}-";

            $max = Project::withTrashed()
                ->where('organization_id', $organizationId)
                ->where('project_number', 'like', $full.'%')
                ->lockForUpdate()
                ->pluck('project_number')
                ->map(fn (string $number) => (int) substr($number, strrpos($number, '-') + 1))
                ->max();

            $sequence = str_pad((int) ($max ?? 0) + 1, 3, '0', STR_PAD_LEFT);

            return "{$full}{$sequence}";
        });
    }
}
