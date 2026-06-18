<?php

namespace Tests\Feature\Calibration;

use App\Models\Organization;
use App\Models\User;
use App\Modules\AssetManagement\Models\Asset;
use App\Modules\Calibration\Models\CalibrationCertificate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalibrationHttpTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $manager;
    private User $readOnly;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        $this->org = Organization::factory()->create();

        $this->manager = User::factory()->create(['organization_id' => $this->org->id]);
        $this->manager->assignRole('Business Development Manager');

        $this->readOnly = User::factory()->create(['organization_id' => $this->org->id]);
        $this->readOnly->assignRole('Read Only');
    }

    public function test_user_with_access_can_view_calibration_section(): void
    {
        $this->actingAs($this->manager)->get('/calibration')->assertOk();
        $this->actingAs($this->manager)->get('/calibration/certificates')->assertOk();
    }

    public function test_roleless_user_cannot_reach_calibration(): void
    {
        $stranger = User::factory()->create(['organization_id' => $this->org->id]);
        $this->actingAs($stranger)->get('/calibration')->assertForbidden();
    }

    public function test_manager_can_record_a_certificate_against_an_asset(): void
    {
        $asset = Asset::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->manager->id]);

        $this->actingAs($this->manager)->post('/calibration/certificates', [
            'asset_id' => $asset->id,
            'result' => 'pass',
            'calibrated_at' => '2026-06-18',
            'interval_months' => 12,
            'standard_used' => 'Ref accel A-12',
        ])->assertRedirect();

        $cert = CalibrationCertificate::where('organization_id', $this->org->id)->first();
        $this->assertNotNull($cert);
        $this->assertSame('2027-06-18', $cert->due_at->toDateString());
        // mirrored to the asset's maintenance timeline
        $this->assertDatabaseHas('asset_maintenance_records', ['asset_id' => $asset->id, 'type' => 'calibration']);
    }

    public function test_duplicate_certificate_number_is_rejected(): void
    {
        CalibrationCertificate::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->manager->id, 'certificate_number' => 'CAL-DUP']);

        $this->actingAs($this->manager)->post('/calibration/certificates', [
            'certificate_number' => 'CAL-DUP', 'result' => 'pass', 'calibrated_at' => '2026-06-18',
        ])->assertSessionHasErrors('certificate_number');
    }

    public function test_read_only_cannot_record_a_certificate(): void
    {
        $this->actingAs($this->readOnly)->get('/calibration/certificates')->assertOk();
        $this->actingAs($this->readOnly)->post('/calibration/certificates', [
            'result' => 'pass', 'calibrated_at' => '2026-06-18',
        ])->assertForbidden();
    }

    public function test_certificates_are_organization_scoped(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherUser = User::factory()->create(['organization_id' => $otherOrg->id]);
        $foreign = CalibrationCertificate::factory()->create(['organization_id' => $otherOrg->id, 'created_by' => $otherUser->id]);

        $this->actingAs($this->manager)->get("/calibration/certificates/{$foreign->id}")->assertForbidden();
    }

    public function test_overdue_filter_returns_only_overdue_certificates(): void
    {
        CalibrationCertificate::factory()->overdue()->create(['organization_id' => $this->org->id, 'created_by' => $this->manager->id, 'certificate_number' => 'CAL-OLD']);
        CalibrationCertificate::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->manager->id, 'certificate_number' => 'CAL-NEW']);

        $this->actingAs($this->manager)->get('/calibration/certificates?due=overdue')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('certificates.data', 1)
                ->where('certificates.data.0.certificate_number', 'CAL-OLD'));
    }
}
