<?php

namespace Tests\Feature\Procurement;

use App\Models\Organization;
use App\Models\User;
use App\Modules\Procurement\Enums\PurchaseOrderStatus;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\Supplier;
use App\Modules\Procurement\Models\SupplierContact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The vendor self-service portal: a supplier contact signs in to a read-only
 * view of only their own supplier's documents. Gated by a feature flag and
 * strictly per-vendor scoped.
 */
class VendorPortalTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private const KEY = 'vendor_portal_contact_id';

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::factory()->create();
    }

    private function enable(): void
    {
        config(['procurement.vendor_portal_enabled' => true]);
    }

    private function supplier(string $name): Supplier
    {
        $creator = User::factory()->create(['organization_id' => $this->org->id]);

        return Supplier::create([
            'organization_id' => $this->org->id, 'created_by' => $creator->id, 'owner_id' => $creator->id,
            'code' => 'SUP-'.random_int(1000, 9999), 'name' => $name, 'status' => 'active', 'currency' => 'USD',
        ]);
    }

    private function contact(Supplier $s, bool $enabled = true, string $password = 'secret123'): SupplierContact
    {
        $c = $s->contacts()->create([
            'organization_id' => $this->org->id, 'name' => 'Vic Vendor', 'email' => 'vic'.$s->id.'@acme.test',
            'portal_enabled' => $enabled,
        ]);
        $c->forceFill(['portal_password' => Hash::make($password)])->save();

        return $c->fresh();
    }

    private function order(Supplier $s): PurchaseOrder
    {
        return PurchaseOrder::create([
            'organization_id' => $this->org->id, 'created_by' => $s->created_by,
            'procurement_supplier_id' => $s->id,
            'number' => 'PO-'.random_int(10000, 99999), 'status' => PurchaseOrderStatus::Sent,
            'order_date' => now()->toDateString(), 'currency' => 'USD',
            'subtotal' => 100, 'tax_rate' => 0, 'tax_amount' => 0, 'shipping_amount' => 0, 'total' => 100,
        ]);
    }

    public function test_portal_is_hidden_when_the_flag_is_off(): void
    {
        // flag defaults off
        $this->get('/vendor/login')->assertNotFound();
        $this->get('/vendor')->assertNotFound();
    }

    public function test_login_page_renders_when_enabled(): void
    {
        $this->enable();
        $this->get('/vendor/login')->assertOk()->assertSee('Vendor sign in');
    }

    public function test_valid_credentials_sign_in(): void
    {
        $this->enable();
        $contact = $this->contact($this->supplier('Acme'));

        $this->post('/vendor/login', ['email' => $contact->email, 'password' => 'secret123'])
            ->assertRedirect(route('vendor.dashboard'));

        $this->assertNotNull($contact->fresh()->portal_last_login_at);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->enable();
        $contact = $this->contact($this->supplier('Acme'));

        $this->post('/vendor/login', ['email' => $contact->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email');

        $this->assertNull($contact->fresh()->portal_last_login_at);
    }

    public function test_a_disabled_contact_cannot_sign_in(): void
    {
        $this->enable();
        $contact = $this->contact($this->supplier('Acme'), enabled: false);

        $this->post('/vendor/login', ['email' => $contact->email, 'password' => 'secret123'])
            ->assertSessionHasErrors('email');
    }

    public function test_dashboard_shows_only_the_signed_in_vendors_documents(): void
    {
        $this->enable();
        $mine = $this->supplier('Mine Co');
        $other = $this->supplier('Other Co');
        $contact = $this->contact($mine);
        $myOrder = $this->order($mine);
        $foreignOrder = $this->order($other);

        $this->withSession([self::KEY => $contact->id])
            ->get('/vendor')
            ->assertOk()
            ->assertSee($myOrder->number)
            ->assertDontSee($foreignOrder->number);
    }

    public function test_pdf_is_scoped_to_the_signed_in_vendor(): void
    {
        $this->enable();
        $mine = $this->supplier('Mine Co');
        $other = $this->supplier('Other Co');
        $contact = $this->contact($mine);
        $myOrder = $this->order($mine);
        $foreignOrder = $this->order($other);

        $this->withSession([self::KEY => $contact->id])
            ->get("/vendor/documents/purchase-orders/{$myOrder->id}/pdf")->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->withSession([self::KEY => $contact->id])
            ->get("/vendor/documents/purchase-orders/{$foreignOrder->id}/pdf")->assertNotFound();
    }

    public function test_unauthenticated_access_redirects_to_login(): void
    {
        $this->enable();
        $this->get('/vendor')->assertRedirect(route('vendor.login'));
    }
}
