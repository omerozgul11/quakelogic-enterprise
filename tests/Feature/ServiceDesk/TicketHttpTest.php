<?php

namespace Tests\Feature\ServiceDesk;

use App\Models\Organization;
use App\Models\User;
use App\Modules\ServiceDesk\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketHttpTest extends TestCase
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

    public function test_user_with_access_can_view_service_desk(): void
    {
        $this->actingAs($this->manager)->get('/tickets')->assertOk();
        $this->actingAs($this->manager)->get('/tickets/queue')->assertOk();
        $this->actingAs($this->manager)->get('/tickets/queue/create')->assertOk();
    }

    public function test_roleless_user_cannot_reach_service_desk(): void
    {
        $stranger = User::factory()->create(['organization_id' => $this->org->id]);
        $this->actingAs($stranger)->get('/tickets')->assertForbidden();
    }

    public function test_manager_can_open_a_ticket(): void
    {
        $this->actingAs($this->manager)->post('/tickets/queue', [
            'subject' => 'Sensor offline',
            'type' => 'support',
            'priority' => 'high',
        ])->assertRedirect();

        $ticket = Ticket::where('organization_id', $this->org->id)->first();
        $this->assertNotNull($ticket);
        $this->assertStringStartsWith('TKT-', $ticket->number);
        $this->assertNotNull($ticket->due_at);
    }

    public function test_read_only_cannot_open_a_ticket(): void
    {
        $this->actingAs($this->readOnly)->get('/tickets/queue')->assertOk();
        $this->actingAs($this->readOnly)->post('/tickets/queue', [
            'subject' => 'Nope', 'type' => 'support', 'priority' => 'normal',
        ])->assertForbidden();
    }

    public function test_tickets_are_organization_scoped(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherUser = User::factory()->create(['organization_id' => $otherOrg->id]);
        $foreign = Ticket::factory()->create(['organization_id' => $otherOrg->id, 'created_by' => $otherUser->id]);

        $this->actingAs($this->manager)->get("/tickets/queue/{$foreign->id}")->assertForbidden();
    }

    public function test_manager_can_comment_assign_and_transition(): void
    {
        $ticket = Ticket::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->manager->id]);

        $this->actingAs($this->manager)->post("/tickets/queue/{$ticket->id}/comments", ['body' => 'Working on it'])->assertRedirect();
        $this->assertSame(1, $ticket->comments()->count());

        $this->actingAs($this->manager)->post("/tickets/queue/{$ticket->id}/assign", ['assigned_to' => $this->manager->id])->assertRedirect();
        $this->assertSame($this->manager->id, $ticket->fresh()->assigned_to);

        $this->actingAs($this->manager)->post("/tickets/queue/{$ticket->id}/status", ['status' => 'resolved', 'resolution' => 'Fixed'])->assertRedirect();
        $this->assertSame('resolved', $ticket->fresh()->status->value);
    }

    public function test_read_only_cannot_comment(): void
    {
        $ticket = Ticket::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->manager->id]);

        $this->actingAs($this->readOnly)->post("/tickets/queue/{$ticket->id}/comments", ['body' => 'Nope'])->assertForbidden();
    }
}
