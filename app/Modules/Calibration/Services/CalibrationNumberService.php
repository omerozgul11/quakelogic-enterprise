<?php

namespace App\Modules\Calibration\Services;

use App\Modules\Calibration\Models\CalibrationCertificate;
use Illuminate\Support\Facades\DB;

/**
 * Sequential calibration certificate numbers, CAL-YYYY-NNNN, generated under a
 * row lock. Mirrors the other Hub number services.
 */
class CalibrationNumberService
{
    public function generate(int $organizationId): string
    {
        return DB::transaction(function () use ($organizationId) {
            $year = now()->year;

            $count = CalibrationCertificate::withTrashed()
                ->where('organization_id', $organizationId)
                ->whereYear('created_at', $year)
                ->lockForUpdate()
                ->count();

            return sprintf('CAL-%d-%04d', $year, $count + 1);
        });
    }
}
