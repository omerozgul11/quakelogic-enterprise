<?php

namespace Tests\Feature\Procurement;

use App\Models\Organization;
use App\Models\User;
use App\Modules\Procurement\Enums\PurchaseOrderStatus;
use App\Modules\Procurement\Models\ProcurementAttachment;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Polymorphic file attachments on procurement documents: upload to the private
 * local disk, authorized download, delete, validation, and org isolation.
 */
class AttachmentTest extends TestCase
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

        foreach (['access procurement', 'view procurement', 'manage purchase orders'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $this->user->givePermissionTo(['access procurement', 'view procurement', 'manage purchase orders']);
    }

    private function order(?Organization $org = null): PurchaseOrder
    {
        $org ??= $this->org;
        $supplier = Supplier::create([
            'organization_id' => $org->id, 'created_by' => $this->user->id, 'owner_id' => $this->user->id,
            'code' => 'SUP-'.random_int(1000, 9999), 'name' => 'Acme', 'status' => 'active', 'currency' => 'USD',
        ]);

        return PurchaseOrder::create([
            'organization_id' => $org->id, 'created_by' => $this->user->id,
            'procurement_supplier_id' => $supplier->id,
            'number' => 'PO-'.random_int(1000, 9999), 'status' => PurchaseOrderStatus::Draft,
            'order_date' => now()->toDateString(), 'currency' => 'USD',
            'subtotal' => 0, 'tax_rate' => 0, 'tax_amount' => 0, 'shipping_amount' => 0, 'total' => 0,
        ]);
    }

    public function test_upload_stores_a_file_and_records_it(): void
    {
        $po = $this->order();

        $this->actingAs($this->user)
            ->post("/procurement/attachments/purchase-orders/{$po->id}", [
                'file' => UploadedFile::fake()->create('quote.pdf', 40, 'application/pdf'),
            ])->assertRedirect();

        $a = ProcurementAttachment::firstOrFail();
        $this->assertSame($po->id, $a->attachable_id);
        $this->assertSame(PurchaseOrder::class, $a->attachable_type);
        $this->assertSame('quote.pdf', $a->original_name);
        Storage::disk('local')->assertExists($a->path);
    }

    public function test_upload_rejects_a_disallowed_type(): void
    {
        $po = $this->order();

        $this->actingAs($this->user)
            ->post("/procurement/attachments/purchase-orders/{$po->id}", [
                'file' => UploadedFile::fake()->create('evil.exe', 10, 'application/x-msdownload'),
            ])->assertSessionHasErrors('file');

        $this->assertSame(0, ProcurementAttachment::count());
    }

    public function test_download_returns_the_file_then_delete_removes_it(): void
    {
        $po = $this->order();
        $this->actingAs($this->user)->post("/procurement/attachments/purchase-orders/{$po->id}", [
            'file' => UploadedFile::fake()->create('quote.pdf', 20, 'application/pdf'),
        ])->assertRedirect();
        $a = ProcurementAttachment::firstOrFail();

        $this->actingAs($this->user)->get("/procurement/attachments/{$a->id}/download")->assertOk();

        $this->actingAs($this->user)->delete("/procurement/attachments/{$a->id}")->assertRedirect();
        $this->assertSoftDeleted('procurement_attachments', ['id' => $a->id]);
        Storage::disk('local')->assertMissing($a->path);
    }

    public function test_a_user_from_another_org_cannot_access_the_attachment(): void
    {
        $po = $this->order();
        $this->actingAs($this->user)->post("/procurement/attachments/purchase-orders/{$po->id}", [
            'file' => UploadedFile::fake()->create('quote.pdf', 20, 'application/pdf'),
        ])->assertRedirect();
        $a = ProcurementAttachment::firstOrFail();

        $otherOrg = Organization::factory()->create();
        $intruder = User::factory()->create(['organization_id' => $otherOrg->id, 'is_active' => true, 'email_verified_at' => now()]);
        $intruder->givePermissionTo(['access procurement', 'view procurement', 'manage purchase orders']);

        $this->actingAs($intruder)->get("/procurement/attachments/{$a->id}/download")->assertForbidden();
        $this->actingAs($intruder)->post("/procurement/attachments/purchase-orders/{$po->id}", [
            'file' => UploadedFile::fake()->create('x.pdf', 5, 'application/pdf'),
        ])->assertNotFound();
    }
}
