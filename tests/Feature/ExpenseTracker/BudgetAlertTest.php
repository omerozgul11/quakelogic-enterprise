<?php

namespace Tests\Feature\ExpenseTracker;

use App\Models\Organization;
use App\Models\User;
use App\Modules\ExpenseTracker\Enums\ExpenseStatus;
use App\Modules\ExpenseTracker\Models\Expense;
use App\Modules\ExpenseTracker\Models\ExpenseCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use App\Notifications\ActivityNotification;
use Tests\TestCase;

class BudgetAlertTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $owner;
    private User $approver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        $this->org = Organization::factory()->create();

        $this->owner = User::factory()->create(['organization_id' => $this->org->id]);
        $this->owner->assignRole('Business Development Manager');

        $this->approver = User::factory()->create(['organization_id' => $this->org->id]);
        $this->approver->assignRole('Business Development Manager');
    }

    public function test_over_budget_approval_notifies_managers(): void
    {
        Notification::fake();

        $category = ExpenseCategory::factory()->withBudget(100)->create([
            'organization_id' => $this->org->id, 'created_by' => $this->owner->id,
        ]);

        // A submitted expense whose approval will push the category to 150 > 100.
        $expense = Expense::factory()->submitted()->create([
            'organization_id' => $this->org->id, 'created_by' => $this->owner->id, 'owner_id' => $this->owner->id,
            'expense_category_id' => $category->id, 'amount' => 150, 'expense_date' => now()->toDateString(),
        ]);

        $this->actingAs($this->approver)->post("/expenses/list/{$expense->id}/approve")->assertRedirect();
        $this->assertSame(ExpenseStatus::Approved, $expense->fresh()->status);

        Notification::assertSentTo($this->approver, ActivityNotification::class);
    }

    public function test_no_alert_when_under_budget(): void
    {
        Notification::fake();

        $category = ExpenseCategory::factory()->withBudget(1000)->create([
            'organization_id' => $this->org->id, 'created_by' => $this->owner->id,
        ]);

        $expense = Expense::factory()->submitted()->create([
            'organization_id' => $this->org->id, 'created_by' => $this->owner->id, 'owner_id' => $this->owner->id,
            'expense_category_id' => $category->id, 'amount' => 50, 'expense_date' => now()->toDateString(),
        ]);

        $this->actingAs($this->approver)->post("/expenses/list/{$expense->id}/approve")->assertRedirect();

        Notification::assertNothingSent();
    }
}
