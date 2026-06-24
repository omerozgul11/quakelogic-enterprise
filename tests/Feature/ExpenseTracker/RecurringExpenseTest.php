<?php

namespace Tests\Feature\ExpenseTracker;

use App\Models\Organization;
use App\Models\User;
use App\Modules\ExpenseTracker\Enums\ExpenseStatus;
use App\Modules\ExpenseTracker\Models\Expense;
use App\Modules\ExpenseTracker\Models\RecurringExpense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class RecurringExpenseTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        $this->org = Organization::factory()->create();
        $this->owner = User::factory()->create(['organization_id' => $this->org->id]);
        $this->owner->assignRole('Business Development Manager');
    }

    private function recurring(array $overrides = []): RecurringExpense
    {
        return RecurringExpense::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->owner->id,
            'owner_id' => $this->owner->id,
            ...$overrides,
        ]);
    }

    public function test_generate_recurring_creates_due_expense_and_advances_next_run(): void
    {
        $recurring = $this->recurring([
            'frequency' => 'monthly',
            'next_run_date' => now()->subDay()->toDateString(),
            'amount' => 99.00,
        ]);
        $expectedNext = $recurring->next_run_date->copy()->addMonthNoOverflow()->toDateString();

        Artisan::call('expenses:generate-recurring');

        $expense = Expense::where('recurring_expense_id', $recurring->id)->firstOrFail();
        $this->assertSame('99.00', $expense->amount);
        $this->assertSame(ExpenseStatus::Draft, $expense->status);
        $this->assertStringStartsWith('EXP-', $expense->number);

        $fresh = $recurring->fresh();
        $this->assertSame($expectedNext, $fresh->next_run_date->toDateString());
        $this->assertNotNull($fresh->last_generated_at);
    }

    public function test_auto_approve_schedule_produces_approved_expense(): void
    {
        $recurring = $this->recurring([
            'next_run_date' => now()->subDay()->toDateString(),
            'auto_approve' => true,
        ]);

        Artisan::call('expenses:generate-recurring');

        $expense = Expense::where('recurring_expense_id', $recurring->id)->firstOrFail();
        $this->assertSame(ExpenseStatus::Approved, $expense->status);
        $this->assertNotNull($expense->approved_at);
    }

    public function test_inactive_or_ended_schedules_are_skipped(): void
    {
        $inactive = $this->recurring(['next_run_date' => now()->subDay()->toDateString(), 'is_active' => false]);
        $ended = $this->recurring([
            'next_run_date' => now()->subDay()->toDateString(),
            'end_date' => now()->subWeek()->toDateString(),
        ]);

        Artisan::call('expenses:generate-recurring');

        $this->assertSame(0, Expense::whereIn('recurring_expense_id', [$inactive->id, $ended->id])->count());
    }

    public function test_command_can_be_scoped_to_one_organization(): void
    {
        $mine = $this->recurring(['next_run_date' => now()->subDay()->toDateString()]);

        $otherOrg = Organization::factory()->create();
        $otherUser = User::factory()->create(['organization_id' => $otherOrg->id]);
        $theirs = RecurringExpense::factory()->create([
            'organization_id' => $otherOrg->id, 'created_by' => $otherUser->id, 'owner_id' => $otherUser->id,
            'next_run_date' => now()->subDay()->toDateString(),
        ]);

        Artisan::call('expenses:generate-recurring', ['--org' => $this->org->id]);

        $this->assertSame(1, Expense::where('recurring_expense_id', $mine->id)->count());
        $this->assertSame(0, Expense::where('recurring_expense_id', $theirs->id)->count());
    }

    public function test_generate_now_endpoint_creates_one_expense(): void
    {
        $recurring = $this->recurring(['next_run_date' => now()->addMonth()->toDateString()]);

        $this->actingAs($this->owner)->post("/expenses/recurring/{$recurring->id}/generate")->assertRedirect();

        $this->assertSame(1, Expense::where('recurring_expense_id', $recurring->id)->count());
    }
}
