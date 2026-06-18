<?php

namespace App\Modules\Calibration\Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use App\Modules\AssetManagement\Models\Asset;
use App\Modules\Calibration\Enums\CalibrationResult;
use App\Modules\Calibration\Models\CalibrationCertificate;
use App\Modules\Calibration\Services\CalibrationService;
use Illuminate\Database\Seeder;

/**
 * Optional demo data for Calibration — records a calibration certificate for each
 * existing asset. NOT wired into DatabaseSeeder; needs assets (run AssetDemoSeeder
 * first). Invoke explicitly:
 *
 *   php artisan db:seed --class="App\Modules\Calibration\Database\Seeders\CalibrationDemoSeeder"
 */
class CalibrationDemoSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::query()->orderBy('id')->first();
        $user = $org ? User::where('organization_id', $org->id)->orderBy('id')->first() : null;

        if (! $org || ! $user) {
            $this->command?->warn('CalibrationDemoSeeder: no organization/user found — skipping.');

            return;
        }

        $service = app(CalibrationService::class);
        $assets = Asset::where('organization_id', $org->id)->orderBy('id')->limit(5)->get();
        $recorded = 0;

        foreach ($assets as $i => $asset) {
            $exists = CalibrationCertificate::where('organization_id', $org->id)->where('asset_id', $asset->id)->exists();
            if ($exists) {
                continue;
            }
            // Alternate one overdue / one current to populate the dashboard.
            $calibratedAt = $i % 2 === 0 ? now()->subMonths(14) : now()->subMonths(2);
            $service->record($org->id, $user->id, [
                'asset_id' => $asset->id,
                'result' => CalibrationResult::Pass->value,
                'standard_used' => 'Reference accelerometer #A-12 (NIST cal 2026-01)',
                'technician' => $user->name,
                'serial_number' => $asset->serial_number,
                'calibrated_at' => $calibratedAt->toDateString(),
                'interval_months' => 12,
            ]);
            $recorded++;
        }

        $this->command?->info("CalibrationDemoSeeder: recorded {$recorded} calibration certificate(s) for \"{$org->name}\".");
    }
}
