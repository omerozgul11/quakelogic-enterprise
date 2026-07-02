<?php

namespace Tests\Feature\Crm;

use App\Models\Company;
use App\Models\Crm\Invoice;
use App\Models\Crm\Project;
use App\Models\Organization;
use App\Models\User;
use App\Modules\ExpenseTracker\Models\Expense;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Auto/optional project creation from a CRM invoice, and expenses surfaced on
 * the project details page.
 */
class ProjectFromInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->seed(RolesPermissionsSeeder::class); // all roles (incl. the CEO/Super Admin the project notifier targets) + permissions
        $this->org = Organization::factory()->create();
        $this->user = User::factory()->create(['organization_id' => $this->org->id, 'is_active' => true, 'email_verified_at' => now()]);
        $this->user->assignRole('Super Admin');
    }

    private function invoicePayload(array $overrides = []): array
    {
        return array_merge([
            'kind' => 'invoice',
            'currency' => 'USD',
            'issue_date' => now()->toDateString(),
            'items' => [['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 500]],
        ], $overrides);
    }

    public function test_invoice_with_the_create_project_flag_spawns_and_links_a_project(): void
    {
        $this->actingAs($this->user)
            ->post('/crm/invoices', $this->invoicePayload(['create_project' => true]))
            ->assertRedirect();

        $invoice = Invoice::where('organization_id', $this->org->id)->firstOrFail();
        $this->assertNotNull($invoice->crm_project_id);
        $project = Project::findOrFail($invoice->crm_project_id);
        $this->assertEqualsWithDelta(500.0, (float) $project->budget, 0.01);
        $this->assertSame(1, $project->invoices()->count());
    }

    public function test_invoice_without_the_flag_creates_no_project(): void
    {
        $this->actingAs($this->user)
            ->post('/crm/invoices', $this->invoicePayload())
            ->assertRedirect();

        $this->assertNull(Invoice::where('organization_id', $this->org->id)->firstOrFail()->crm_project_id);
        $this->assertSame(0, Project::where('organization_id', $this->org->id)->count());
    }

    public function test_invoice_can_link_to_an_existing_project(): void
    {
        $project = Project::create([
            'organization_id' => $this->org->id, 'created_by' => $this->user->id, 'owner_id' => $this->user->id,
            'name' => 'Existing', 'project_number' => 'QL-PROJ-TEST-1', 'status' => 'new', 'progress' => 0,
        ]);

        $this->actingAs($this->user)
            ->post('/crm/invoices', $this->invoicePayload(['crm_project_id' => $project->id]))
            ->assertRedirect();

        $this->assertSame($project->id, Invoice::where('organization_id', $this->org->id)->firstOrFail()->crm_project_id);
        $this->assertSame(1, Project::where('organization_id', $this->org->id)->count());  // no new project
    }

    public function test_manual_create_project_action_is_idempotent(): void
    {
        $company = Company::factory()->create(['organization_id' => $this->org->id]);
        $invoice = Invoice::create([
            'organization_id' => $this->org->id, 'created_by' => $this->user->id, 'owner_id' => $this->user->id,
            'company_id' => $company->id, 'number' => 'INV-X-1', 'kind' => 'invoice', 'status' => 'sent',
            'currency' => 'USD', 'total' => 1200,
        ]);

        $this->actingAs($this->user)->post("/crm/invoices/{$invoice->id}/create-project")->assertRedirect();
        $invoice->refresh();
        $this->assertNotNull($invoice->crm_project_id);
        $this->assertEqualsWithDelta(1200.0, (float) Project::find($invoice->crm_project_id)->budget, 0.01);

        // Second attempt is refused (already linked) — no duplicate project.
        $this->actingAs($this->user)->post("/crm/invoices/{$invoice->id}/create-project")->assertRedirect();
        $this->assertSame(1, Project::where('organization_id', $this->org->id)->count());
    }

    public function test_project_show_lists_linked_expenses(): void
    {
        $project = Project::create([
            'organization_id' => $this->org->id, 'created_by' => $this->user->id, 'owner_id' => $this->user->id,
            'name' => 'P', 'project_number' => 'QL-PROJ-TEST-2', 'status' => 'new', 'progress' => 0,
        ]);
        Expense::create([
            'organization_id' => $this->org->id, 'created_by' => $this->user->id, 'owner_id' => $this->user->id,
            'crm_project_id' => $project->id, 'number' => 'EXP-1', 'description' => 'Flights', 'amount' => 300,
            'currency' => 'USD', 'status' => 'draft', 'expense_date' => now()->toDateString(),
        ]);

        $this->actingAs($this->user)->get("/projects/{$project->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Show')
                ->has('expenses', 1)
                ->where('financials.spent', fn ($v) => (float) $v === 300.0));
    }

    public function test_can_add_an_expense_to_a_project(): void
    {
        $project = Project::create([
            'organization_id' => $this->org->id, 'created_by' => $this->user->id, 'owner_id' => $this->user->id,
            'name' => 'P', 'project_number' => 'QL-PROJ-TEST-3', 'status' => 'new', 'progress' => 0,
        ]);

        $this->actingAs($this->user)->post("/projects/{$project->id}/expenses", [
            'description' => 'Site survey', 'amount' => 250, 'currency' => 'USD', 'expense_date' => now()->toDateString(),
        ])->assertRedirect();

        $expense = Expense::where('crm_project_id', $project->id)->firstOrFail();
        $this->assertEqualsWithDelta(250.0, (float) $expense->amount, 0.01);
        $this->assertSame('Site survey', $expense->description);
    }
}
