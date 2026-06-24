<?php

namespace Tests\Feature\ExpenseTracker;

use App\Models\Organization;
use App\Models\User;
use App\Modules\ExpenseTracker\Jobs\PushExpenseToQuickBooks;
use App\Modules\ExpenseTracker\Jobs\SyncQuickBooksConnection;
use App\Modules\ExpenseTracker\Models\Expense;
use App\Modules\ExpenseTracker\Models\QuickBooksConnection;
use App\Modules\ExpenseTracker\Services\ExpenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QuickBooksRealtimeTest extends TestCase
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

    private function connection(array $overrides = []): QuickBooksConnection
    {
        return QuickBooksConnection::create([
            'organization_id' => $this->org->id,
            'connected_by' => $this->owner->id,
            'realm_id' => 'REALM-1',
            'environment' => 'sandbox',
            'is_demo' => true,
            'push_enabled' => true,
            'token_expires_at' => now()->addHour(),
            ...$overrides,
        ]);
    }

    public function test_approving_an_expense_immediately_pushes_to_quickbooks(): void
    {
        $this->connection();
        // Queue is sync in tests, so the dispatched job runs inline → real-time.
        $expense = Expense::factory()->submitted()->create([
            'organization_id' => $this->org->id, 'created_by' => $this->owner->id, 'owner_id' => $this->owner->id,
            'source' => 'manual', 'quickbooks_id' => null,
        ]);

        $this->actingAs($this->approver)->post("/expenses/list/{$expense->id}/approve")->assertRedirect();

        $this->assertNotNull($expense->fresh()->quickbooks_id, 'Approval should push to QuickBooks immediately.');
    }

    public function test_no_push_job_when_push_disabled(): void
    {
        Queue::fake();
        $this->connection(['push_enabled' => false]);

        $expense = Expense::factory()->submitted()->create([
            'organization_id' => $this->org->id, 'created_by' => $this->owner->id, 'owner_id' => $this->owner->id,
            'source' => 'manual',
        ]);

        $this->actingAs($this->approver)->post("/expenses/list/{$expense->id}/approve")->assertRedirect();

        Queue::assertNotPushed(PushExpenseToQuickBooks::class);
    }

    public function test_editing_an_approved_expense_dispatches_a_push(): void
    {
        Queue::fake();
        $this->connection();

        $expense = Expense::factory()->approved()->create([
            'organization_id' => $this->org->id, 'created_by' => $this->owner->id, 'owner_id' => $this->owner->id,
            'source' => 'manual', 'quickbooks_id' => 'QB-EXISTING',
        ]);

        $this->actingAs($this->approver)->put("/expenses/list/{$expense->id}", [
            'amount' => 999.99,
            'currency' => 'USD',
            'expense_date' => now()->toDateString(),
        ])->assertRedirect();

        Queue::assertPushed(PushExpenseToQuickBooks::class);
    }

    public function test_imported_expenses_do_not_echo_back_to_quickbooks(): void
    {
        Queue::fake();
        $connection = $this->connection();

        // Pull imports 3 expenses (source=quickbooks); none should trigger a push job.
        app(\App\Modules\ExpenseTracker\Services\QuickBooksSyncService::class)->syncOrganization($connection);

        Queue::assertNotPushed(PushExpenseToQuickBooks::class);
        $this->assertSame(3, Expense::where('source', 'quickbooks')->count());
    }

    public function test_webhook_rejects_bad_signature_and_accepts_signed_payload(): void
    {
        config()->set('services.quickbooks.webhook_token', 'test-verifier');
        $connection = $this->connection();
        Queue::fake();

        $payload = json_encode(['eventNotifications' => [['realmId' => $connection->realm_id, 'dataChangeEvent' => ['entities' => [['name' => 'Purchase']]]]]]);

        // Unsigned → 401.
        $this->call('POST', '/api/quickbooks/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json'], $payload)
            ->assertStatus(401);

        // Correctly signed → 200 and a sync job is queued for that company.
        $signature = base64_encode(hash_hmac('sha256', $payload, 'test-verifier', true));
        $this->call('POST', '/api/quickbooks/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_INTUIT_SIGNATURE' => $signature,
        ], $payload)->assertOk();

        Queue::assertPushed(SyncQuickBooksConnection::class);
    }
}
