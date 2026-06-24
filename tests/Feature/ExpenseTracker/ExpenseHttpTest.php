<?php

namespace Tests\Feature\ExpenseTracker;

use App\Models\Organization;
use App\Models\User;
use App\Modules\ExpenseTracker\Enums\ExpenseStatus;
use App\Modules\ExpenseTracker\Models\Expense;
use App\Modules\ExpenseTracker\Models\ExpenseAttachment;
use App\Modules\ExpenseTracker\Models\ExpenseCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExpenseHttpTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $manager;   // owner / creator (has manage expenses)
    private User $approver;  // a different manager who can approve
    private User $readOnly;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        $this->org = Organization::factory()->create();

        $this->manager = User::factory()->create(['organization_id' => $this->org->id]);
        $this->manager->assignRole('Business Development Manager');

        $this->approver = User::factory()->create(['organization_id' => $this->org->id]);
        $this->approver->assignRole('Business Development Manager');

        $this->readOnly = User::factory()->create(['organization_id' => $this->org->id]);
        $this->readOnly->assignRole('Read Only');
    }

    private function expense(array $overrides = []): Expense
    {
        return Expense::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->manager->id,
            'owner_id' => $this->manager->id,
            ...$overrides,
        ]);
    }

    public function test_user_with_access_can_view_all_sections(): void
    {
        foreach (['/expenses', '/expenses/list', '/expenses/categories', '/expenses/recurring', '/expenses/reports'] as $url) {
            $this->actingAs($this->manager)->get($url)->assertOk();
        }
    }

    public function test_roleless_user_cannot_reach_expenses(): void
    {
        $stranger = User::factory()->create(['organization_id' => $this->org->id]);
        $this->actingAs($stranger)->get('/expenses')->assertForbidden();
    }

    public function test_read_only_can_view_but_not_manage(): void
    {
        $this->actingAs($this->readOnly)->get('/expenses/list')->assertOk();
        $this->actingAs($this->readOnly)->post('/expenses/categories', ['name' => 'Travel'])->assertForbidden();

        $expense = $this->expense(['status' => ExpenseStatus::Submitted->value, 'owner_id' => $this->manager->id]);
        $this->actingAs($this->readOnly)->post("/expenses/list/{$expense->id}/approve")->assertForbidden();
    }

    public function test_user_can_create_their_own_expense(): void
    {
        $this->actingAs($this->manager)->post('/expenses/list', [
            'amount' => 125.50,
            'currency' => 'USD',
            'expense_date' => now()->toDateString(),
            'vendor' => 'Acme Cloud',
        ])->assertRedirect();

        $expense = Expense::where('organization_id', $this->org->id)->firstOrFail();
        $this->assertStringStartsWith('EXP-', $expense->number);
        $this->assertSame($this->manager->id, $expense->owner_id);
        $this->assertSame(ExpenseStatus::Draft, $expense->status);
        $this->assertSame('125.50', $expense->amount);
    }

    public function test_create_rejects_non_positive_amount(): void
    {
        $this->actingAs($this->manager)->post('/expenses/list', [
            'amount' => 0,
            'expense_date' => now()->toDateString(),
        ])->assertSessionHasErrors('amount');
    }

    public function test_expenses_are_organization_scoped(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherUser = User::factory()->create(['organization_id' => $otherOrg->id]);
        $foreign = Expense::factory()->create([
            'organization_id' => $otherOrg->id, 'created_by' => $otherUser->id, 'owner_id' => $otherUser->id,
        ]);

        $this->actingAs($this->manager)->get("/expenses/list/{$foreign->id}")->assertForbidden();
    }

    public function test_submit_approve_reimburse_lifecycle(): void
    {
        $expense = $this->expense(['status' => ExpenseStatus::Draft->value]);

        // Owner submits.
        $this->actingAs($this->manager)->post("/expenses/list/{$expense->id}/submit")->assertRedirect();
        $this->assertSame(ExpenseStatus::Submitted, $expense->fresh()->status);

        // A different manager approves.
        $this->actingAs($this->approver)->post("/expenses/list/{$expense->id}/approve")->assertRedirect();
        $fresh = $expense->fresh();
        $this->assertSame(ExpenseStatus::Approved, $fresh->status);
        $this->assertSame($this->approver->id, $fresh->approved_by);
        $this->assertNotNull($fresh->approved_at);

        // Reimburse.
        $this->actingAs($this->approver)->post("/expenses/list/{$expense->id}/reimburse")->assertRedirect();
        $this->assertSame(ExpenseStatus::Reimbursed, $expense->fresh()->status);
    }

    public function test_owner_cannot_approve_their_own_expense(): void
    {
        $expense = $this->expense(['status' => ExpenseStatus::Submitted->value]);
        $this->actingAs($this->manager)->post("/expenses/list/{$expense->id}/approve")->assertForbidden();
    }

    public function test_reject_records_reason(): void
    {
        $expense = $this->expense(['status' => ExpenseStatus::Submitted->value]);
        $this->actingAs($this->approver)->post("/expenses/list/{$expense->id}/reject", ['reason' => 'Missing receipt'])->assertRedirect();

        $fresh = $expense->fresh();
        $this->assertSame(ExpenseStatus::Rejected, $fresh->status);
        $this->assertSame('Missing receipt', $fresh->reject_reason);
    }

    public function test_receipt_upload_download_and_isolation(): void
    {
        Storage::fake('local');
        $expense = $this->expense();

        $this->actingAs($this->manager)->post("/expenses/list/{$expense->id}/receipts", [
            'file' => UploadedFile::fake()->create('receipt.pdf', 120, 'application/pdf'),
        ])->assertRedirect();

        $attachment = ExpenseAttachment::where('expense_id', $expense->id)->firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);

        $this->actingAs($this->manager)->get("/expenses/list/{$expense->id}/receipts/{$attachment->id}")->assertOk();

        // The same attachment under a different expense must 404.
        $other = $this->expense();
        $this->actingAs($this->manager)->get("/expenses/list/{$other->id}/receipts/{$attachment->id}")->assertNotFound();
    }

    public function test_cannot_delete_category_with_expenses(): void
    {
        $category = ExpenseCategory::factory()->create([
            'organization_id' => $this->org->id, 'created_by' => $this->manager->id,
        ]);
        $this->expense(['expense_category_id' => $category->id]);

        $this->actingAs($this->manager)->delete("/expenses/categories/{$category->id}")->assertRedirect();
        $this->assertNotNull($category->fresh(), 'Category with expenses should not be deleted.');
    }
}
