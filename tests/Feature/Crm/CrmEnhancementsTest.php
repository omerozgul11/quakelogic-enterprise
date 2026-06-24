<?php

namespace Tests\Feature\Crm;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Crm\Activity;
use App\Models\Crm\Automation;
use App\Models\Crm\FollowUp;
use App\Models\Crm\Lead;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CrmEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $admin;   // Super Admin → can manage automations
    private User $rep;     // Business Development Manager

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        $this->org = Organization::factory()->create();

        $this->admin = User::factory()->create(['organization_id' => $this->org->id]);
        $this->admin->assignRole('Super Admin');

        $this->rep = User::factory()->create(['organization_id' => $this->org->id]);
        $this->rep->assignRole('Business Development Manager');
    }

    private function makeLead(array $overrides = []): Lead
    {
        return Lead::create(array_merge([
            'organization_id' => $this->org->id,
            'created_by' => $this->rep->id,
            'owner_id' => $this->rep->id,
            'title' => 'Acme Corp',
            'company_name' => 'Acme Corp',
            'contact_name' => 'Jane Doe',
            'product_name' => 'Sensor',
            'phone' => '775-555-0100',
            'status' => 'new',
            'estimated_value' => 10000,
            'probability' => 50,
            'last_activity_at' => now(),
        ], $overrides));
    }

    private function leadMorph(): string
    {
        return (new Lead)->getMorphClass();
    }

    // ---- Feature 1: activity timeline -------------------------------------

    public function test_creating_a_lead_logs_a_created_activity(): void
    {
        $this->actingAs($this->rep)->post('/crm/leads', [
            'company_name' => 'Globex', 'contact_name' => 'John Roe',
            'phone' => '775-555-0123', 'product_name' => 'Datalogger',
        ])->assertRedirect();

        $lead = Lead::where('company_name', 'Globex')->firstOrFail();
        $this->assertDatabaseHas('crm_activities', [
            'subject_type' => $this->leadMorph(), 'subject_id' => $lead->id, 'type' => 'created',
        ]);
    }

    public function test_changing_stage_logs_a_stage_change_activity(): void
    {
        $lead = $this->makeLead();

        $this->actingAs($this->rep)->post("/crm/leads/{$lead->id}/status", ['status' => 'qualified'])->assertRedirect();

        $a = Activity::where('subject_id', $lead->id)->where('type', 'stage_change')->firstOrFail();
        $this->assertSame('new', $a->meta['from']);
        $this->assertSame('qualified', $a->meta['to']);
    }

    public function test_user_can_log_and_delete_a_manual_activity(): void
    {
        $lead = $this->makeLead();

        $this->actingAs($this->rep)->post('/crm/activities', [
            'subject' => 'lead', 'subject_id' => $lead->id, 'type' => 'call', 'body' => 'Left a voicemail',
        ])->assertRedirect();

        $activity = Activity::where('type', 'call')->firstOrFail();
        $this->assertSame('Left a voicemail', $activity->body);

        $this->actingAs($this->rep)->delete("/crm/activities/{$activity->id}")->assertRedirect();
        $this->assertSoftDeleted('crm_activities', ['id' => $activity->id]);
    }

    public function test_cannot_delete_a_system_activity(): void
    {
        $lead = $this->makeLead();
        $this->actingAs($this->rep)->post("/crm/leads/{$lead->id}/status", ['status' => 'contacted']);
        $system = Activity::where('subject_id', $lead->id)->where('type', 'stage_change')->firstOrFail();

        $this->actingAs($this->rep)->delete("/crm/activities/{$system->id}")->assertStatus(403);
    }

    public function test_lead_show_page_returns_timeline_and_follow_ups(): void
    {
        $lead = $this->makeLead();

        $this->actingAs($this->rep)->get("/crm/leads/{$lead->id}")->assertInertia(fn (Assert $p) => $p
            ->component('Crm/Leads/Show')
            ->where('lead.id', $lead->id)
            ->has('activities')
            ->has('followUps')
        );
    }

    // ---- Feature 2: follow-ups --------------------------------------------

    public function test_can_create_and_complete_a_follow_up(): void
    {
        $lead = $this->makeLead();

        $this->actingAs($this->rep)->post('/crm/follow-ups', [
            'title' => 'Call back', 'due_date' => now()->toDateString(),
            'priority' => 'high', 'subject' => 'lead', 'subject_id' => $lead->id,
        ])->assertRedirect();

        $followUp = FollowUp::where('title', 'Call back')->firstOrFail();
        $this->assertSame($this->leadMorph(), $followUp->subject_type);
        $this->assertSame($lead->id, $followUp->subject_id);

        $this->actingAs($this->rep)->post("/crm/follow-ups/{$followUp->id}/complete")->assertRedirect();
        $this->assertSame('done', $followUp->fresh()->status);

        // Completing logs a timeline entry on the lead.
        $this->assertDatabaseHas('crm_activities', [
            'subject_id' => $lead->id, 'type' => 'task',
        ]);
    }

    public function test_dashboard_buckets_follow_ups_by_due_date(): void
    {
        FollowUp::create(['organization_id' => $this->org->id, 'created_by' => $this->rep->id, 'assigned_to' => $this->rep->id, 'title' => 'Overdue', 'due_date' => now()->subDays(2)->toDateString(), 'status' => 'open']);
        FollowUp::create(['organization_id' => $this->org->id, 'created_by' => $this->rep->id, 'assigned_to' => $this->rep->id, 'title' => 'Today', 'due_date' => now()->toDateString(), 'status' => 'open']);
        FollowUp::create(['organization_id' => $this->org->id, 'created_by' => $this->rep->id, 'assigned_to' => $this->rep->id, 'title' => 'Soon', 'due_date' => now()->addDays(3)->toDateString(), 'status' => 'open']);

        $this->actingAs($this->rep)->get('/crm')->assertInertia(fn (Assert $p) => $p
            ->where('followUps.counts.overdue', 1)
            ->where('followUps.counts.today', 1)
            ->where('followUps.counts.upcoming', 1)
        );
    }

    // ---- Feature 7: reporting ---------------------------------------------

    public function test_reports_compute_win_rate(): void
    {
        $this->makeLead(['status' => 'won', 'estimated_value' => 5000]);
        $this->makeLead(['status' => 'won', 'estimated_value' => 5000]);
        $this->makeLead(['status' => 'lost']);
        $this->makeLead(['status' => 'qualified']);

        $this->actingAs($this->rep)->get('/crm/reports?period=0')->assertInertia(fn (Assert $p) => $p
            ->component('Crm/Reports/Index')
            ->where('summary.won', 2)
            ->where('summary.lost', 1)
            ->where('summary.win_rate', 66.7)
            ->where('summary.open', 1)
            ->has('forecast.by_month', 6)
        );
    }

    // ---- Feature 8: dedupe / merge ----------------------------------------

    public function test_duplicate_companies_are_detected_and_merged(): void
    {
        $primary = Company::create(['organization_id' => $this->org->id, 'created_by' => $this->admin->id, 'owner_id' => $this->admin->id, 'name' => 'Acme Inc']);
        $dup = Company::create(['organization_id' => $this->org->id, 'created_by' => $this->admin->id, 'owner_id' => $this->admin->id, 'name' => 'ACME', 'phone' => '123']);
        $lead = $this->makeLead(['company_id' => $dup->id]);

        // Both normalize to "acme" → one duplicate group.
        $this->actingAs($this->admin)->get('/crm/duplicates')->assertInertia(fn (Assert $p) => $p
            ->has('companyGroups', 1)
        );

        $this->actingAs($this->admin)->post('/crm/duplicates/merge', [
            'type' => 'company', 'primary_id' => $primary->id, 'duplicate_ids' => [$dup->id],
        ])->assertRedirect();

        $this->assertSame($primary->id, $lead->fresh()->company_id);
        $this->assertSoftDeleted('companies', ['id' => $dup->id]);
        // Blank field backfilled from the duplicate.
        $this->assertSame('123', $primary->fresh()->phone);
    }

    // ---- Feature 10: automation engine ------------------------------------

    public function test_non_manager_cannot_create_automation(): void
    {
        $this->actingAs($this->rep)->post('/crm/automations', [
            'name' => 'X', 'trigger_event' => 'lead.created', 'actions' => [['type' => 'log_activity', 'body' => 'hi']],
        ])->assertStatus(403);
    }

    public function test_stage_change_automation_creates_a_follow_up(): void
    {
        $this->actingAs($this->admin)->post('/crm/automations', [
            'name' => 'Proposal nudge',
            'trigger_event' => 'lead.stage_changed',
            'conditions' => ['stage' => 'proposal'],
            'actions' => [[
                'type' => 'create_followup', 'title' => 'Send the proposal', 'due_in_days' => 2, 'priority' => 'high', 'assign' => 'owner',
            ]],
        ])->assertRedirect();

        $this->assertDatabaseCount('crm_automations', 1);

        $lead = $this->makeLead();
        // Move to proposal → should fire.
        $this->actingAs($this->rep)->post("/crm/leads/{$lead->id}/status", ['status' => 'proposal'])->assertRedirect();

        $followUp = FollowUp::where('title', 'Send the proposal')->first();
        $this->assertNotNull($followUp, 'automation should have created a follow-up');
        $this->assertSame($lead->id, $followUp->subject_id);
        $this->assertSame($this->rep->id, $followUp->assigned_to);
        $this->assertSame(1, Automation::first()->run_count);
    }

    public function test_assign_owner_automation_reassigns_lead(): void
    {
        $this->actingAs($this->admin)->post('/crm/automations', [
            'name' => 'Route big deals',
            'trigger_event' => 'lead.created',
            'conditions' => ['min_value' => 50000],
            'actions' => [['type' => 'assign_owner', 'user_id' => $this->admin->id]],
        ])->assertRedirect();

        // Small lead — should NOT match.
        $this->actingAs($this->rep)->post('/crm/leads', [
            'company_name' => 'Small', 'contact_name' => 'A B', 'phone' => '1', 'product_name' => 'X', 'estimated_value' => 100,
        ]);
        $this->assertSame($this->rep->id, Lead::where('company_name', 'Small')->first()->owner_id);

        // Big lead — should be reassigned to admin.
        $this->actingAs($this->rep)->post('/crm/leads', [
            'company_name' => 'Whale', 'contact_name' => 'C D', 'phone' => '1', 'product_name' => 'X', 'estimated_value' => 75000,
        ]);
        $this->assertSame($this->admin->id, Lead::where('company_name', 'Whale')->first()->owner_id);
    }
}
