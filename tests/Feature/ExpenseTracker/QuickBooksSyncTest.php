<?php

namespace Tests\Feature\ExpenseTracker;

use App\Models\Organization;
use App\Models\User;
use App\Modules\ExpenseTracker\Enums\ExpenseStatus;
use App\Modules\ExpenseTracker\Models\Expense;
use App\Modules\ExpenseTracker\Models\ExpenseCategory;
use App\Modules\ExpenseTracker\Models\QuickBooksConnection;
use App\Modules\ExpenseTracker\Services\QuickBooksSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class QuickBooksSyncTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        $this->org = Organization::factory()->create();
        $this->manager = User::factory()->create(['organization_id' => $this->org->id]);
        $this->manager->assignRole('Business Development Manager');
    }

    private function connection(array $overrides = []): QuickBooksConnection
    {
        return QuickBooksConnection::create([
            'organization_id' => $this->org->id,
            'connected_by' => $this->manager->id,
            'realm_id' => 'TEST-REALM',
            'environment' => 'sandbox',
            'is_demo' => true,
            'token_expires_at' => now()->addHour(),
            ...$overrides,
        ]);
    }

    public function test_pull_imports_quickbooks_expenses_and_maps_categories(): void
    {
        $connection = $this->connection();

        $result = app(QuickBooksSyncService::class)->syncOrganization($connection);

        $this->assertSame(3, $result['imported']);

        $imported = Expense::where('organization_id', $this->org->id)->where('source', 'quickbooks')->get();
        $this->assertCount(3, $imported);
        $this->assertTrue($imported->every(fn (Expense $e) => $e->status === ExpenseStatus::Approved));
        $this->assertTrue($imported->every(fn (Expense $e) => $e->quickbooks_id !== null));

        // QuickBooks account names became expense categories.
        $this->assertTrue(ExpenseCategory::where('organization_id', $this->org->id)->where('name', 'Travel')->exists());
    }

    public function test_pull_is_idempotent(): void
    {
        $connection = $this->connection();
        $sync = app(QuickBooksSyncService::class);

        $sync->syncOrganization($connection);
        $sync->syncOrganization($connection->fresh());

        $this->assertSame(3, Expense::where('organization_id', $this->org->id)->where('source', 'quickbooks')->count());
    }

    public function test_push_sends_approved_local_expenses_when_enabled(): void
    {
        $connection = $this->connection(['push_enabled' => true]);

        $local = Expense::factory()->approved()->create([
            'organization_id' => $this->org->id, 'created_by' => $this->manager->id, 'owner_id' => $this->manager->id,
            'source' => 'manual', 'quickbooks_id' => null,
        ]);

        app(QuickBooksSyncService::class)->syncOrganization($connection);

        $this->assertNotNull($local->fresh()->quickbooks_id);
        $this->assertNotNull($local->fresh()->quickbooks_synced_at);
    }

    public function test_push_is_skipped_when_disabled(): void
    {
        $connection = $this->connection(['push_enabled' => false]);

        $local = Expense::factory()->approved()->create([
            'organization_id' => $this->org->id, 'created_by' => $this->manager->id, 'owner_id' => $this->manager->id,
            'source' => 'manual', 'quickbooks_id' => null,
        ]);

        app(QuickBooksSyncService::class)->syncOrganization($connection);

        $this->assertNull($local->fresh()->quickbooks_id);
    }

    public function test_sync_is_organization_scoped(): void
    {
        $connection = $this->connection();
        $otherOrg = Organization::factory()->create();

        app(QuickBooksSyncService::class)->syncOrganization($connection);

        $this->assertSame(0, Expense::where('organization_id', $otherOrg->id)->count());
        $this->assertSame(3, Expense::where('organization_id', $this->org->id)->count());
    }

    public function test_command_noops_without_connections(): void
    {
        Artisan::call('quickbooks:sync');
        $this->assertSame(0, Expense::count());
    }

    public function test_command_syncs_connected_org(): void
    {
        $this->connection();
        Artisan::call('quickbooks:sync', ['--org' => $this->org->id]);
        $this->assertSame(3, Expense::where('source', 'quickbooks')->count());
    }
}
