<?php

namespace App\Modules\Calibration\Services;

use App\Modules\AssetManagement\Enums\MaintenanceType;
use App\Modules\AssetManagement\Models\Asset;
use App\Modules\AssetManagement\Services\AssetService;
use App\Modules\Calibration\Models\CalibrationCertificate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Records calibration certificates. Computes the next-due date from the interval
 * when not supplied, and — when the certificate is for an asset — also logs a
 * calibration entry on that asset's maintenance history (Calibration → Asset),
 * so a deployed instrument's calibration shows in one place. The Asset module
 * has no reverse dependency on Calibration.
 */
class CalibrationService
{
    public function __construct(
        private readonly CalibrationNumberService $numbers,
        private readonly AssetService $assets,
    ) {}

    public function record(int $organizationId, int $actorId, array $data): CalibrationCertificate
    {
        return DB::transaction(function () use ($organizationId, $actorId, $data) {
            $calibratedAt = Carbon::parse($data['calibrated_at'] ?? now());
            $interval = $data['interval_months'] ?? null;
            $dueAt = ! empty($data['due_at'])
                ? Carbon::parse($data['due_at'])
                : ($interval ? (clone $calibratedAt)->addMonths((int) $interval) : null);

            $certificate = CalibrationCertificate::create([
                'organization_id' => $organizationId,
                'created_by' => $actorId,
                'asset_id' => $data['asset_id'] ?? null,
                'inventory_product_id' => $data['inventory_product_id'] ?? null,
                'performed_by' => $data['performed_by'] ?? $actorId,
                'certificate_number' => $data['certificate_number'] ?? $this->numbers->generate($organizationId),
                'result' => $data['result'] ?? 'pass',
                'nist_traceable' => $data['nist_traceable'] ?? true,
                'method' => $data['method'] ?? null,
                'standard_used' => $data['standard_used'] ?? null,
                'technician' => $data['technician'] ?? null,
                'serial_number' => $data['serial_number'] ?? null,
                'calibrated_at' => $calibratedAt->toDateString(),
                'due_at' => $dueAt?->toDateString(),
                'interval_months' => $interval,
                'measurements' => $data['measurements'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            // Mirror onto the asset's maintenance timeline when linked.
            if ($certificate->asset_id) {
                $asset = Asset::where('organization_id', $organizationId)->find($certificate->asset_id);
                if ($asset) {
                    $this->assets->logMaintenance($asset, [
                        'type' => MaintenanceType::Calibration->value,
                        'description' => "Calibration {$certificate->certificate_number} — ".$certificate->result->label(),
                        'performed_at' => $certificate->calibrated_at->toDateString(),
                        'next_due_at' => $certificate->due_at?->toDateString(),
                        'notes' => $certificate->notes,
                    ], $actorId);
                }
            }

            return $certificate;
        });
    }
}
