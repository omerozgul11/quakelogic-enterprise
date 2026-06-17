<?php

namespace Tests\Feature\Admin;

use App\Models\Organization;
use App\Models\ProposalSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private User $admin;
    private User $readOnly;
    private User $proposalManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        $this->organization = Organization::factory()->create();

        $this->admin = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->admin->assignRole('Super Admin');

        $this->readOnly = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->readOnly->assignRole('Read Only');

        $this->proposalManager = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->proposalManager->assignRole('Proposal Manager');
    }

    public function test_admin_can_view_the_audit_log(): void
    {
        $this->actingAs($this->admin)->get('/admin/audit-logs')->assertOk();
    }

    public function test_non_admin_cannot_view_the_audit_log(): void
    {
        $this->actingAs($this->readOnly)->get('/admin/audit-logs')->assertForbidden();
    }

    public function test_guest_cannot_view_the_audit_log(): void
    {
        $this->get('/admin/audit-logs')->assertRedirect();
    }

    public function test_creating_and_editing_a_proposal_is_audited_to_the_user(): void
    {
        $this->actingAs($this->proposalManager)->post('/proposals', [
            'project_name' => 'Audit Me',
            'company' => 'GSA',
            'solicitation_number' => 'GSA-2026-0001',
            'proposal_value' => 1000,
            'due_date' => now()->addDays(30)->format('Y-m-d'),
        ])->assertRedirect();

        $proposal = ProposalSubmission::where('organization_id', $this->organization->id)->firstOrFail();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'created',
            'user_id' => $this->proposalManager->id,
            'auditable_type' => ProposalSubmission::class,
            'auditable_id' => $proposal->id,
        ]);

        $this->actingAs($this->proposalManager)->put("/proposals/{$proposal->id}", [
            'project_name' => 'Audit Me — edited',
        ])->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'updated',
            'user_id' => $this->proposalManager->id,
            'auditable_type' => ProposalSubmission::class,
            'auditable_id' => $proposal->id,
        ]);
    }
}
