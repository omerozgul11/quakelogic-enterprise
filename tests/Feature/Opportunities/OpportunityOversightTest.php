<?php

namespace Tests\Feature\Opportunities;

use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpportunityOversightTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $exec;
    private User $rep;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        $this->org = Organization::factory()->create();

        $this->exec = User::factory()->create(['organization_id' => $this->org->id]);
        $this->exec->assignRole('Super Admin'); // has "view executive dashboard"

        $this->rep = User::factory()->create(['organization_id' => $this->org->id]);
        $this->rep->assignRole('Sales Representative'); // no executive dashboard
    }

    public function test_executive_can_view_command_center(): void
    {
        Opportunity::factory()->count(3)->create(['organization_id' => $this->org->id]);

        $this->actingAs($this->exec)
            ->get('/dashboard/opportunities')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Dashboard/Oversight')->has('summary.counts')->has('summary.attention'));
    }

    public function test_non_executive_cannot_view_command_center(): void
    {
        $this->actingAs($this->rep)
            ->get('/dashboard/opportunities')
            ->assertForbidden();
    }

    public function test_briefing_creates_one_inbox_message_per_executive(): void
    {
        Opportunity::factory()->count(2)->create(['organization_id' => $this->org->id, 'owner_id' => null]);

        $this->artisan('executive:briefing')->assertExitCode(0);

        $this->assertDatabaseHas('follow_ups', [
            'assigned_to' => $this->exec->id,
            'type' => 'briefing',
            'is_automated' => true,
        ]);
        // The non-exec rep gets no briefing.
        $this->assertDatabaseMissing('follow_ups', [
            'assigned_to' => $this->rep->id,
            'type' => 'briefing',
        ]);

        // Idempotent: a second run the same day does not duplicate.
        $this->artisan('executive:briefing')->assertExitCode(0);
        $this->assertSame(1, \App\Models\FollowUp::where('assigned_to', $this->exec->id)->where('type', 'briefing')->count());
    }
}
