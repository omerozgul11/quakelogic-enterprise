<?php

namespace App\Modules\AssetManagement\Services;

use App\Modules\AssetManagement\Models\Asset;
use Illuminate\Support\Facades\DB;

/**
 * Sequential asset tags, AST-YYYY-NNNN, generated under a row lock. Mirrors the
 * other Hub number services.
 */
class AssetTagService
{
    public function generate(int $organizationId): string
    {
        return DB::transaction(function () use ($organizationId) {
            $year = now()->year;

            $count = Asset::withTrashed()
                ->where('organization_id', $organizationId)
                ->whereYear('created_at', $year)
                ->lockForUpdate()
                ->count();

            return sprintf('AST-%d-%04d', $year, $count + 1);
        });
    }
}
