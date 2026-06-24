<?php

namespace Tests\Feature\ExpenseTracker;

use App\Models\Organization;
use App\Models\User;
use App\Modules\ExpenseTracker\Models\Expense;
use App\Modules\ExpenseTracker\Models\QuickBooksConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuickBooksHttpTest extends TestCase
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

    public function test_settings_page_loads(): void
    {
        $this->actingAs($this->manager)->get('/expenses/quickbooks')->assertOk();
    }

    public function test_demo_connect_then_sync_imports_expenses(): void
    {
        $this->actingAs($this->manager)->get('/expenses/quickbooks/connect')->assertRedirect();

        $connection = QuickBooksConnection::where('organization_id', $this->org->id)->firstOrFail();
        $this->assertTrue($connection->is_demo);

        $this->actingAs($this->manager)->post('/expenses/quickbooks/sync')->assertRedirect();
        $this->assertSame(3, Expense::where('organization_id', $this->org->id)->where('source', 'quickbooks')->count());
    }

    public function test_toggle_push_and_disconnect(): void
    {
        $this->actingAs($this->manager)->get('/expenses/quickbooks/connect')->assertRedirect();

        $this->actingAs($this->manager)->post('/expenses/quickbooks/push-toggle')->assertRedirect();
        $this->assertTrue(QuickBooksConnection::where('organization_id', $this->org->id)->firstOrFail()->push_enabled);

        $this->actingAs($this->manager)->delete('/expenses/quickbooks')->assertRedirect();
        $this->assertSame(0, QuickBooksConnection::where('organization_id', $this->org->id)->count());
    }

    public function test_read_only_can_view_but_not_manage_the_connection(): void
    {
        $this->actingAs($this->readOnly)->get('/expenses/quickbooks')->assertOk();
        $this->actingAs($this->readOnly)->get('/expenses/quickbooks/connect')->assertForbidden();
        $this->actingAs($this->readOnly)->post('/expenses/quickbooks/sync')->assertForbidden();
    }
}
