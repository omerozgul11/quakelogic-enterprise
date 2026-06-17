<?php

namespace Tests\Feature\CRM;

use App\Models\Crm\Invoice;
use App\Models\Crm\Lead;
use App\Models\Crm\Project;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmSectionTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private User $bdm;
    private User $readOnly;
    private User $noRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        $this->organization = Organization::factory()->create();

        $this->bdm = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->bdm->assignRole('Business Development Manager');

        $this->readOnly = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->readOnly->assignRole('Read Only');

        $this->noRole = User::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_access_crm_is_granted_to_every_seeded_role(): void
    {
        foreach (['Super Admin', 'CEO', 'Business Development Manager', 'Proposal Manager', 'Proposal Writer', 'Sales Representative', 'Finance', 'Read Only'] as $role) {
            $user = User::factory()->create(['organization_id' => $this->organization->id]);
            $user->assignRole($role);
            $this->assertTrue($user->can('access crm'), "$role should have access crm");
        }
    }

    public function test_section_is_gated_by_access_crm(): void
    {
        $this->actingAs($this->bdm)->get('/crm')->assertStatus(200);
        $this->actingAs($this->readOnly)->get('/crm')->assertStatus(200);
        // A user with no role has no permissions at all → blocked by the gate.
        $this->actingAs($this->noRole)->get('/crm')->assertStatus(403);
    }

    public function test_bdm_can_create_a_lead(): void
    {
        $response = $this->actingAs($this->bdm)->post('/crm/leads', [
            'title' => 'City of Reno — SCADA upgrade',
            'estimated_value' => 120000,
            'status' => 'qualified',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('crm_leads', [
            'title' => 'City of Reno — SCADA upgrade',
            'organization_id' => $this->organization->id,
            'status' => 'qualified',
            'created_by' => $this->bdm->id,
        ]);
    }

    public function test_read_only_cannot_create_a_lead(): void
    {
        $this->actingAs($this->readOnly)->get('/crm/leads')->assertStatus(200);
        $this->actingAs($this->readOnly)->post('/crm/leads', ['title' => 'Nope'])->assertStatus(403);
    }

    public function test_leads_are_organization_scoped(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherUser = User::factory()->create(['organization_id' => $otherOrg->id]);
        $otherUser->assignRole('Business Development Manager');

        $lead = Lead::create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->bdm->id,
            'title' => 'Confidential pursuit',
        ]);

        $this->actingAs($otherUser)->put("/crm/leads/{$lead->id}", ['title' => 'Hijacked'])->assertStatus(403);
        $this->assertDatabaseHas('crm_leads', ['id' => $lead->id, 'title' => 'Confidential pursuit']);
    }

    public function test_lead_status_can_be_moved_in_the_pipeline(): void
    {
        $lead = Lead::create([
            'organization_id' => $this->organization->id,
            'created_by' => $this->bdm->id,
            'title' => 'Pipeline test',
            'status' => 'new',
        ]);

        $this->actingAs($this->bdm)->post("/crm/leads/{$lead->id}/status", ['status' => 'proposal'])->assertRedirect();
        $this->assertDatabaseHas('crm_leads', ['id' => $lead->id, 'status' => 'proposal']);
    }

    public function test_bdm_can_create_a_project_and_add_a_task(): void
    {
        $this->actingAs($this->bdm)->post('/crm/projects', [
            'name' => 'SCADA Rollout',
            'status' => 'active',
        ])->assertRedirect();

        $project = Project::where('name', 'SCADA Rollout')->firstOrFail();
        $this->assertSame($this->organization->id, $project->organization_id);

        $this->actingAs($this->bdm)->post("/crm/projects/{$project->id}/tasks", [
            'title' => 'Site survey',
            'status' => 'completed',
        ])->assertRedirect();

        $this->assertDatabaseHas('crm_tasks', ['crm_project_id' => $project->id, 'title' => 'Site survey']);
        // One task, completed → progress recomputed to 100%.
        $this->assertSame(100, $project->fresh()->progress);
    }

    public function test_invoice_totals_and_sequential_numbering(): void
    {
        $this->actingAs($this->bdm)->post('/crm/invoices', [
            'kind' => 'invoice',
            'tax_rate' => 10,
            'discount_amount' => 0,
            'items' => [
                ['description' => 'Engineering', 'quantity' => 2, 'unit_price' => 100],
                ['description' => 'Travel', 'quantity' => 1, 'unit_price' => 50],
            ],
        ])->assertRedirect();

        $year = now()->year;
        $invoice = Invoice::where('organization_id', $this->organization->id)->firstOrFail();
        $this->assertSame("INV-{$year}-0001", $invoice->number);
        $this->assertEquals(250.00, (float) $invoice->subtotal);
        $this->assertEquals(25.00, (float) $invoice->tax_amount);
        $this->assertEquals(275.00, (float) $invoice->total);
        $this->assertDatabaseCount('crm_invoice_items', 2);

        // Second invoice increments the sequence.
        $this->actingAs($this->bdm)->post('/crm/invoices', ['kind' => 'invoice', 'items' => []])->assertRedirect();
        $this->assertDatabaseHas('crm_invoices', ['organization_id' => $this->organization->id, 'number' => "INV-{$year}-0002"]);
    }

    public function test_recording_a_full_payment_marks_invoice_paid(): void
    {
        $this->actingAs($this->bdm)->post('/crm/invoices', [
            'kind' => 'invoice',
            'items' => [['description' => 'Work', 'quantity' => 1, 'unit_price' => 500]],
        ])->assertRedirect();

        $invoice = Invoice::where('organization_id', $this->organization->id)->firstOrFail();
        $this->assertEquals(500.00, (float) $invoice->total);

        $this->actingAs($this->bdm)->post("/crm/invoices/{$invoice->id}/payments", [
            'amount' => 500,
            'paid_at' => now()->toDateString(),
            'method' => 'wire',
        ])->assertRedirect();

        $invoice->refresh();
        $this->assertEquals(500.00, (float) $invoice->amount_paid);
        $this->assertSame('paid', $invoice->status->value);
    }

    public function test_estimates_get_their_own_number_series(): void
    {
        $this->actingAs($this->bdm)->post('/crm/invoices', ['kind' => 'estimate', 'items' => []])->assertRedirect();

        $year = now()->year;
        $this->assertDatabaseHas('crm_invoices', [
            'organization_id' => $this->organization->id,
            'kind' => 'estimate',
            'number' => "EST-{$year}-0001",
        ]);
    }
}
