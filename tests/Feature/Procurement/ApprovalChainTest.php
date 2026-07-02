<?php

namespace Tests\Feature\Procurement;

use App\Models\Organization;
use App\Models\User;
use App\Modules\Procurement\Enums\ApprovalStatus;
use App\Modules\Procurement\Enums\PurchaseOrderStatus;
use App\Modules\Procurement\Enums\PurchaseRequestStatus;
use App\Modules\Procurement\Models\ApprovalFlow;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\PurchaseRequest;
use App\Modules\Procurement\Models\Supplier;
use App\Modules\Procurement\Services\ApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Multi-level, amount-tiered approval chains + digital signatures. Backward
 * compatible: with no flow configured, submit/approve behave as the simple flow.
 */
class ApprovalChainTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->org = Organization::factory()->create();
        $this->user = User::factory()->create(['organization_id' => $this->org->id, 'is_active' => true, 'email_verified_at' => now()]);
        foreach (['access procurement', 'view procurement', 'manage purchase orders', 'approve purchase orders', 'manage purchase requests', 'approve purchase requests'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $this->user->givePermissionTo(['access procurement', 'view procurement', 'manage purchase orders', 'approve purchase orders', 'manage purchase requests', 'approve purchase requests']);
    }

    private function user(): User
    {
        $u = User::factory()->create(['organization_id' => $this->org->id, 'is_active' => true, 'email_verified_at' => now()]);
        $u->givePermissionTo(['access procurement', 'view procurement']);

        return $u;
    }

    private function order(float $total = 100): PurchaseOrder
    {
        $supplier = Supplier::create([
            'organization_id' => $this->org->id, 'created_by' => $this->user->id, 'owner_id' => $this->user->id,
            'code' => 'SUP-'.random_int(1000, 9999), 'name' => 'Acme', 'status' => 'active', 'currency' => 'USD',
        ]);

        return PurchaseOrder::create([
            'organization_id' => $this->org->id, 'created_by' => $this->user->id,
            'procurement_supplier_id' => $supplier->id,
            'number' => 'PO-'.random_int(1000, 9999), 'status' => PurchaseOrderStatus::PendingApproval,
            'order_date' => now()->toDateString(), 'currency' => 'USD',
            'subtotal' => $total, 'tax_rate' => 0, 'tax_amount' => 0, 'shipping_amount' => 0, 'total' => $total,
        ]);
    }

    /** @param array<int,array{0:string,1:mixed,2?:bool}> $steps [type, user|role, requireSig?] */
    private function flow(string $docType, float $minAmount, array $steps): ApprovalFlow
    {
        $flow = ApprovalFlow::create([
            'organization_id' => $this->org->id, 'created_by' => $this->user->id,
            'name' => "Flow {$minAmount}", 'document_type' => $docType, 'min_amount' => $minAmount, 'is_active' => true,
        ]);
        foreach ($steps as $i => [$type, $who, ]) {
            $flow->steps()->create([
                'organization_id' => $this->org->id, 'position' => $i,
                'approver_type' => $type,
                'approver_user_id' => $type === 'user' ? $who : null,
                'approver_role' => $type === 'role' ? $who : null,
                'require_signature' => $steps[$i][2] ?? false,
            ]);
        }

        return $flow;
    }

    public function test_flow_for_picks_the_highest_matching_amount_tier(): void
    {
        $this->flow('purchase_order', 0, [['user', $this->user->id]]);
        $big = $this->flow('purchase_order', 50, [['user', $this->user->id], ['user', $this->user->id]]);

        $svc = app(ApprovalService::class);
        $this->assertSame($big->id, $svc->flowFor($this->order(100))->id);
        // A small PO falls to the base tier.
        $this->assertSame(0.0, (float) $svc->flowFor($this->order(10))->min_amount);
    }

    public function test_chain_approves_step_by_step_then_approves_the_document(): void
    {
        $this->flow('purchase_order', 0, [['user', $this->user->id], ['user', $this->user->id]]);
        $po = $this->order(100);
        app(ApprovalService::class)->start($po, $this->user->id);

        // First approval advances to step 2; PO not yet approved.
        $this->actingAs($this->user)->post("/procurement/approvals/purchase-orders/{$po->id}/approve")->assertRedirect();
        $this->assertSame(ApprovalStatus::Pending, $po->latestApproval()->status);
        $this->assertSame(PurchaseOrderStatus::PendingApproval, $po->fresh()->status);

        // Second approval completes the chain → PO approved.
        $this->actingAs($this->user)->post("/procurement/approvals/purchase-orders/{$po->id}/approve")->assertRedirect();
        $this->assertSame(ApprovalStatus::Approved, $po->latestApproval()->status);
        $this->assertSame(PurchaseOrderStatus::Approved, $po->fresh()->status);
    }

    public function test_rejecting_a_step_ends_the_chain_and_rejects_the_request(): void
    {
        $this->flow('purchase_request', 0, [['user', $this->user->id]]);
        $pr = PurchaseRequest::create([
            'organization_id' => $this->org->id, 'created_by' => $this->user->id, 'requester_id' => $this->user->id,
            'number' => 'PR-'.random_int(1000, 9999), 'title' => 'X', 'status' => PurchaseRequestStatus::PendingApproval, 'currency' => 'USD', 'total' => 100,
        ]);
        app(ApprovalService::class)->start($pr, $this->user->id);

        $this->actingAs($this->user)->post("/procurement/approvals/purchase-requests/{$pr->id}/reject", ['note' => 'over budget'])->assertRedirect();

        $this->assertSame(ApprovalStatus::Rejected, $pr->latestApproval()->status);
        $this->assertSame(PurchaseRequestStatus::Rejected, $pr->fresh()->status);
    }

    public function test_a_user_who_is_not_the_assigned_approver_cannot_decide(): void
    {
        $this->flow('purchase_order', 0, [['user', $this->user->id]]);
        $po = $this->order(100);
        app(ApprovalService::class)->start($po, $this->user->id);

        $intruder = $this->user();
        $this->actingAs($intruder)->post("/procurement/approvals/purchase-orders/{$po->id}/approve")
            ->assertRedirect();

        // Still pending — the intruder's attempt was refused.
        $this->assertSame(ApprovalStatus::Pending, $po->latestApproval()->status);
        $this->assertSame(PurchaseOrderStatus::PendingApproval, $po->fresh()->status);
    }

    public function test_a_role_step_is_approvable_by_anyone_holding_the_role(): void
    {
        $role = Role::findOrCreate('Approvers Panel', 'web');
        $approver = $this->user();
        $approver->assignRole($role);

        $this->flow('purchase_order', 0, [['role', 'Approvers Panel']]);
        $po = $this->order(100);
        app(ApprovalService::class)->start($po, $this->user->id);

        // A user without the role cannot.
        $this->actingAs($this->user())->post("/procurement/approvals/purchase-orders/{$po->id}/approve")->assertRedirect();
        $this->assertSame(ApprovalStatus::Pending, $po->latestApproval()->status);

        // A user with the role can.
        $this->actingAs($approver)->post("/procurement/approvals/purchase-orders/{$po->id}/approve")->assertRedirect();
        $this->assertSame(ApprovalStatus::Approved, $po->latestApproval()->status);
        $this->assertSame(PurchaseOrderStatus::Approved, $po->fresh()->status);
    }

    public function test_a_step_requiring_a_signature_stores_it(): void
    {
        $this->flow('purchase_order', 0, [['user', $this->user->id, true]]);
        $po = $this->order(100);
        app(ApprovalService::class)->start($po, $this->user->id);

        // Without a signature it is refused (chain still pending).
        $this->actingAs($this->user)->post("/procurement/approvals/purchase-orders/{$po->id}/approve")->assertRedirect();
        $this->assertSame(ApprovalStatus::Pending, $po->latestApproval()->status);

        // With a signature it is approved and the image is stored.
        $png = 'data:image/png;base64,'.base64_encode('fake-png-bytes');
        $this->actingAs($this->user)->post("/procurement/approvals/purchase-orders/{$po->id}/approve", ['signature' => $png])->assertRedirect();

        $approval = $po->latestApproval();
        $this->assertSame(ApprovalStatus::Approved, $approval->status);
        $path = $approval->steps->first()->signature_path;
        $this->assertNotNull($path);
        Storage::disk('local')->assertExists($path);
    }

    public function test_the_simple_approve_endpoint_is_blocked_while_a_chain_runs(): void
    {
        $this->flow('purchase_order', 0, [['user', $this->user->id]]);
        $po = $this->order(100);
        app(ApprovalService::class)->start($po, $this->user->id);

        // Hitting the legacy single-approve endpoint must not bypass the chain.
        $this->actingAs($this->user)->post("/procurement/purchase-orders/{$po->id}/approve")
            ->assertRedirect();
        $this->assertSame(PurchaseOrderStatus::PendingApproval, $po->fresh()->status);
        $this->assertSame(ApprovalStatus::Pending, $po->latestApproval()->status);
    }

    public function test_admin_can_configure_a_flow_via_the_endpoint(): void
    {
        Permission::findOrCreate('manage approval flows', 'web');
        $this->user->givePermissionTo('manage approval flows');
        Role::findOrCreate('Directors', 'web');
        $approver = $this->user();

        $this->actingAs($this->user)->post('/procurement/approval-flows', [
            'name' => 'Big PO sign-off',
            'document_type' => 'purchase_order',
            'min_amount' => 5000,
            'is_active' => true,
            'steps' => [
                ['approver_type' => 'user', 'approver_user_id' => $approver->id, 'require_signature' => false],
                ['approver_type' => 'role', 'approver_role' => 'Directors', 'require_signature' => true],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $flow = ApprovalFlow::where('name', 'Big PO sign-off')->firstOrFail();
        $this->assertSame(2, $flow->steps()->count());
        $this->assertTrue((bool) $flow->steps()->where('position', 1)->value('require_signature'));
    }

    public function test_flow_config_requires_the_manage_permission(): void
    {
        // $this->user has no 'manage approval flows' permission by default here.
        $this->actingAs($this->user())->post('/procurement/approval-flows', [
            'name' => 'X', 'document_type' => 'purchase_order', 'steps' => [['approver_type' => 'user', 'approver_user_id' => $this->user->id]],
        ])->assertForbidden();
    }

    public function test_without_a_flow_the_simple_approval_still_works(): void
    {
        // No flow configured for POs → the chain never starts, simple approve governs.
        $po = $this->order(100);
        $this->assertNull(app(ApprovalService::class)->start($po, $this->user->id));

        $this->actingAs($this->user)->post("/procurement/purchase-orders/{$po->id}/approve")->assertRedirect();
        $this->assertSame(PurchaseOrderStatus::Approved, $po->fresh()->status);
    }
}
