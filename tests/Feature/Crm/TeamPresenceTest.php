<?php

namespace Tests\Feature\Crm;

use App\Models\Crm\Leave;
use App\Models\Crm\TimeEntry;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TeamPresenceTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $manager;   // Super Admin → has "manage all time cards"
    private User $employee;  // plain access-crm role

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        $this->org = Organization::factory()->create();

        $this->manager = User::factory()->create(['organization_id' => $this->org->id]);
        $this->manager->assignRole('Super Admin');

        $this->employee = User::factory()->create(['organization_id' => $this->org->id]);
        $this->employee->assignRole('Business Development Manager');
    }

    public function test_dashboard_reports_team_presence_buckets(): void
    {
        // manager is clocked in; employee is on leave today; a third is clocked out.
        $idle = User::factory()->create(['organization_id' => $this->org->id]);
        $idle->assignRole('Business Development Manager');

        TimeEntry::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->manager->id,
            'created_by' => $this->manager->id,
            'clock_in' => now()->subHour(),
            'source' => 'clock',
        ]);

        Leave::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->employee->id,
            'created_by' => $this->manager->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'type' => 'vacation',
        ]);

        $this->actingAs($this->manager)->get('/crm')->assertInertia(fn (Assert $p) => $p
            ->where('teamPresence.total', 3)
            ->where('teamPresence.clocked_in', 1)
            ->where('teamPresence.on_leave', 1)
            ->where('teamPresence.clocked_out', 1)
            ->where('teamPresence.can_manage', true)
        );
    }

    public function test_clocked_in_wins_over_leave_for_the_same_person(): void
    {
        // On leave but punched in anyway → counts as clocked-in, not on-leave.
        TimeEntry::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->employee->id,
            'created_by' => $this->employee->id,
            'clock_in' => now()->subHour(),
            'source' => 'clock',
        ]);
        Leave::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->employee->id,
            'created_by' => $this->manager->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'type' => 'sick',
        ]);

        $this->actingAs($this->manager)->get('/crm')->assertInertia(fn (Assert $p) => $p
            ->where('teamPresence.clocked_in', 1)
            ->where('teamPresence.on_leave', 0)
        );
    }

    public function test_employee_sees_presence_but_cannot_manage(): void
    {
        $this->actingAs($this->employee)->get('/crm')->assertInertia(fn (Assert $p) => $p
            ->where('teamPresence.can_manage', false)
        );
    }

    public function test_manager_can_record_and_remove_leave(): void
    {
        $this->actingAs($this->manager)->post('/crm/leave', [
            'user_id' => $this->employee->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'type' => 'vacation',
            'note' => 'Out west',
        ])->assertRedirect();

        $leave = Leave::where('user_id', $this->employee->id)->firstOrFail();
        $this->assertSame($this->org->id, $leave->organization_id);
        $this->assertSame($this->manager->id, $leave->created_by);

        $this->actingAs($this->manager)->delete("/crm/leave/{$leave->id}")->assertRedirect();
        $this->assertSoftDeleted('crm_leaves', ['id' => $leave->id]);
    }

    public function test_non_manager_cannot_record_leave(): void
    {
        $this->actingAs($this->employee)->post('/crm/leave', [
            'user_id' => $this->manager->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ])->assertStatus(403);

        $this->assertDatabaseCount('crm_leaves', 0);
    }

    public function test_cannot_record_leave_for_user_outside_organization(): void
    {
        $outsider = User::factory()->create(); // different org

        $this->actingAs($this->manager)->post('/crm/leave', [
            'user_id' => $outsider->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ])->assertStatus(403);

        $this->assertDatabaseCount('crm_leaves', 0);
    }

    public function test_end_date_must_not_precede_start_date(): void
    {
        $this->actingAs($this->manager)->post('/crm/leave', [
            'user_id' => $this->employee->id,
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->toDateString(),
        ])->assertSessionHasErrors('end_date');
    }
}
