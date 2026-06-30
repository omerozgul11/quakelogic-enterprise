<?php

namespace Tests\Feature\Crm;

use App\Enums\Carrier;
use App\Enums\ProjectStatus;
use App\Models\Crm\Project;
use App\Models\Crm\ProjectEquipment;
use App\Models\Crm\ProjectShipment;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2 of the Project Field Information System: per-project equipment and the
 * shipments that carry it. Additive to the existing Projects app.
 */
class ProjectEquipmentShipmentTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $admin;
    private User $lead;
    private User $outsider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        $this->org = Organization::factory()->create();
        $this->admin = $this->user('Super Admin');
        $this->lead = $this->user('Sales Representative');
        $this->outsider = $this->user('Read Only');
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['organization_id' => $this->org->id, 'is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function project(): Project
    {
        return Project::create([
            'organization_id' => $this->org->id,
            'created_by' => $this->admin->id,
            'owner_id' => $this->lead->id,
            'project_manager_id' => $this->lead->id,
            'name' => 'Field Install',
            'project_number' => 'QL-PROJ-TEST-'.random_int(1000, 9999),
            'status' => ProjectStatus::New->value,
            'created_via' => 'manual',
        ]);
    }

    /** A lead can add equipment; quantity + calibration date persist. */
    public function test_lead_can_add_equipment(): void
    {
        $project = $this->project();

        $this->actingAs($this->lead)->post("/projects/{$project->id}/equipment", [
            'name' => 'Triaxial Seismometer',
            'model' => 'TS-200',
            'serial_number' => 'SN-0001',
            'quantity' => 3,
            'weight' => '240 lbs',
            'center_of_gravity' => 'Mid-rear',
            'rigging_instructions' => 'Sling at marked points; do not tip.',
            'calibration_status' => 'Calibrated',
            'calibration_due' => '2027-01-15',
        ])->assertRedirect();

        $item = ProjectEquipment::where('crm_project_id', $project->id)->firstOrFail();
        $this->assertSame(3, $item->quantity);
        $this->assertSame('SN-0001', $item->serial_number);
        $this->assertSame('2027-01-15', $item->calibration_due->toDateString());
        $this->assertTrue($project->activities()->where('action', 'equipment_added')->exists());
    }

    /** Quantity defaults to 1 when omitted. */
    public function test_quantity_defaults_to_one(): void
    {
        $project = $this->project();
        $this->actingAs($this->lead)->post("/projects/{$project->id}/equipment", ['name' => 'Lone unit'])->assertRedirect();
        $this->assertSame(1, ProjectEquipment::where('crm_project_id', $project->id)->firstOrFail()->quantity);
    }

    /** A shipment carrier yields a tracking URL in the payload. */
    public function test_shipment_carrier_builds_tracking_url(): void
    {
        $project = $this->project();

        $this->actingAs($this->lead)->post("/projects/{$project->id}/shipments", [
            'carrier' => 'ups',
            'tracking_number' => '1Z999AA10123456784',
            'status' => 'in_transit',
            'shock_indicator' => 'intact',
        ])->assertRedirect();

        $shipment = ProjectShipment::where('crm_project_id', $project->id)->firstOrFail();
        $this->assertSame(Carrier::Ups, $shipment->carrier);
        $this->assertSame('intact', $shipment->shock_indicator);

        $this->actingAs($this->admin)->get("/projects/{$project->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('shipments', 1)
                ->where('shipments.0.carrier_label', 'UPS')
                ->where('shipments.0.tracking_url', Carrier::Ups->trackingUrl('1Z999AA10123456784')));
    }

    /** Equipment can be assigned to a shipment on the same project. */
    public function test_equipment_links_to_shipment(): void
    {
        $project = $this->project();
        $this->actingAs($this->lead)->post("/projects/{$project->id}/shipments", ['carrier' => 'fedex']);
        $shipment = ProjectShipment::where('crm_project_id', $project->id)->firstOrFail();

        $this->actingAs($this->lead)->post("/projects/{$project->id}/equipment", [
            'name' => 'Crated unit', 'crm_project_shipment_id' => $shipment->id,
        ])->assertRedirect();

        $this->assertSame($shipment->id, ProjectEquipment::where('crm_project_id', $project->id)->firstOrFail()->crm_project_shipment_id);
    }

    /** A shipment from another project cannot be linked. */
    public function test_cannot_link_shipment_from_another_project(): void
    {
        $project = $this->project();
        $other = $this->project();
        $this->actingAs($this->lead)->post("/projects/{$other->id}/shipments", ['carrier' => 'ups']);
        $foreign = ProjectShipment::where('crm_project_id', $other->id)->firstOrFail();

        $this->actingAs($this->lead)->post("/projects/{$project->id}/equipment", [
            'name' => 'X', 'crm_project_shipment_id' => $foreign->id,
        ])->assertSessionHasErrors('crm_project_shipment_id');
    }

    /** An unknown asset_id is rejected. */
    public function test_unknown_asset_is_rejected(): void
    {
        $project = $this->project();
        $this->actingAs($this->lead)->post("/projects/{$project->id}/equipment", [
            'name' => 'X', 'asset_id' => 999999,
        ])->assertSessionHasErrors('asset_id');
    }

    /** Indicator values are constrained. */
    public function test_invalid_shock_indicator_is_rejected(): void
    {
        $project = $this->project();
        $this->actingAs($this->lead)->post("/projects/{$project->id}/shipments", [
            'shock_indicator' => 'exploded',
        ])->assertSessionHasErrors('shock_indicator');
    }

    /** Equipment & shipment options are serialised into the show payload. */
    public function test_payload_exposes_options(): void
    {
        $project = $this->project();
        $this->actingAs($this->admin)->get("/projects/{$project->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('equipment')
                ->has('shipments')
                ->has('carriers')
                ->has('shipmentStatuses'));
    }

    /** An unassigned read-only user cannot add equipment. */
    public function test_outsider_cannot_add_equipment(): void
    {
        $project = $this->project();
        $this->actingAs($this->outsider)
            ->post("/projects/{$project->id}/equipment", ['name' => 'Sneaky'])
            ->assertForbidden();
        $this->assertSame(0, ProjectEquipment::where('crm_project_id', $project->id)->count());
    }
}
