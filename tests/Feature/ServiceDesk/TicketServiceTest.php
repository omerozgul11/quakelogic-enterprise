<?php

namespace Tests\Feature\ServiceDesk;

use App\Models\Organization;
use App\Models\User;
use App\Modules\ServiceDesk\Enums\TicketPriority;
use App\Modules\ServiceDesk\Enums\TicketStatus;
use App\Modules\ServiceDesk\Models\Ticket;
use App\Modules\ServiceDesk\Services\TicketNumberService;
use App\Modules\ServiceDesk\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketServiceTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $user;
    private TicketService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::factory()->create();
        $this->user = User::factory()->create(['organization_id' => $this->org->id]);
        $this->service = app(TicketService::class);
    }

    public function test_ticket_numbers_are_sequential(): void
    {
        $numbers = app(TicketNumberService::class);
        $first = $numbers->generate($this->org->id);
        Ticket::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->user->id, 'number' => $first]);
        $year = now()->year;
        $this->assertSame("TKT-{$year}-0001", $first);
        $this->assertSame("TKT-{$year}-0002", $numbers->generate($this->org->id));
    }

    public function test_open_derives_sla_due_from_priority(): void
    {
        $ticket = $this->service->open($this->org->id, $this->user->id, ['subject' => 'Urgent', 'priority' => 'urgent']);

        $this->assertSame(TicketStatus::New, $ticket->status);
        // Urgent = 4 hours.
        $this->assertEqualsWithDelta(4, $ticket->opened_at->diffInHours($ticket->due_at), 0.01);
    }

    public function test_first_public_comment_records_response_and_opens_ticket(): void
    {
        $ticket = $this->service->open($this->org->id, $this->user->id, ['subject' => 'Hi', 'priority' => 'normal']);

        $this->service->comment($ticket, $this->user->id, 'On it', false);

        $ticket->refresh();
        $this->assertSame(TicketStatus::Open, $ticket->status);
        $this->assertNotNull($ticket->first_responded_at);
    }

    public function test_internal_note_does_not_count_as_first_response(): void
    {
        $ticket = $this->service->open($this->org->id, $this->user->id, ['subject' => 'Hi', 'priority' => 'normal']);

        $this->service->comment($ticket, $this->user->id, 'Internal triage note', true);

        $this->assertNull($ticket->fresh()->first_responded_at);
    }

    public function test_assigning_opens_a_new_ticket(): void
    {
        $ticket = $this->service->open($this->org->id, $this->user->id, ['subject' => 'Hi', 'priority' => 'normal']);

        $this->service->assign($ticket, $this->user->id);

        $this->assertSame(TicketStatus::Open, $ticket->fresh()->status);
        $this->assertSame($this->user->id, $ticket->fresh()->assigned_to);
    }

    public function test_changing_priority_rebases_the_sla_due_date(): void
    {
        $ticket = $this->service->open($this->org->id, $this->user->id, ['subject' => 'Hi', 'priority' => 'low']);

        $this->service->setPriority($ticket, TicketPriority::Urgent);

        $ticket->refresh();
        $this->assertEqualsWithDelta(4, $ticket->opened_at->diffInHours($ticket->due_at), 0.01);
    }

    public function test_resolving_stamps_resolved_at_and_resolution(): void
    {
        $ticket = $this->service->open($this->org->id, $this->user->id, ['subject' => 'Hi', 'priority' => 'normal']);

        $this->service->transition($ticket, TicketStatus::Resolved, 'Replaced the antenna');

        $ticket->refresh();
        $this->assertSame(TicketStatus::Resolved, $ticket->status);
        $this->assertNotNull($ticket->resolved_at);
        $this->assertSame('Replaced the antenna', $ticket->resolution);
    }
}
