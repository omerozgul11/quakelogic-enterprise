<?php

namespace Tests\Feature\Calibration;

use App\Models\Organization;
use App\Models\User;
use App\Modules\AssetManagement\Models\Asset;
use App\Modules\Calibration\Models\CalibrationCertificate;
use App\Modules\Calibration\Services\CalibrationNumberService;
use App\Modules\Calibration\Services\CalibrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalibrationServiceTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $user;
    private CalibrationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::factory()->create();
        $this->user = User::factory()->create(['organization_id' => $this->org->id]);
        $this->service = app(CalibrationService::class);
    }

    public function test_certificate_numbers_are_sequential(): void
    {
        $numbers = app(CalibrationNumberService::class);
        $first = $numbers->generate($this->org->id);
        CalibrationCertificate::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->user->id, 'certificate_number' => $first]);
        $year = now()->year;
        $this->assertSame("CAL-{$year}-0001", $first);
        $this->assertSame("CAL-{$year}-0002", $numbers->generate($this->org->id));
    }

    public function test_record_computes_due_date_from_interval(): void
    {
        $cert = $this->service->record($this->org->id, $this->user->id, [
            'result' => 'pass', 'calibrated_at' => '2026-06-18', 'interval_months' => 12,
        ]);

        $this->assertSame('2027-06-18', $cert->due_at->toDateString());
        $this->assertStringStartsWith('CAL-', $cert->certificate_number);
    }

    public function test_record_respects_an_explicit_due_date(): void
    {
        $cert = $this->service->record($this->org->id, $this->user->id, [
            'result' => 'pass', 'calibrated_at' => '2026-06-18', 'interval_months' => 12, 'due_at' => '2026-12-31',
        ]);

        $this->assertSame('2026-12-31', $cert->due_at->toDateString());
    }

    public function test_recording_for_an_asset_also_logs_asset_maintenance(): void
    {
        $asset = Asset::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->user->id]);

        $this->service->record($this->org->id, $this->user->id, [
            'asset_id' => $asset->id, 'result' => 'pass', 'calibrated_at' => '2026-06-18', 'interval_months' => 12,
        ]);

        $this->assertDatabaseHas('asset_maintenance_records', [
            'asset_id' => $asset->id,
            'type' => 'calibration',
        ]);
        $this->assertSame(1, $asset->maintenanceRecords()->where('type', 'calibration')->count());
    }

    public function test_overdue_detection(): void
    {
        $overdue = CalibrationCertificate::factory()->overdue()->create(['organization_id' => $this->org->id, 'created_by' => $this->user->id]);
        $current = CalibrationCertificate::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->user->id]);

        $this->assertTrue($overdue->isOverdue());
        $this->assertFalse($current->isOverdue());
    }
}
