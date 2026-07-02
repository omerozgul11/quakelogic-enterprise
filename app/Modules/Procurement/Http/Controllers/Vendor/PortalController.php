<?php

namespace App\Modules\Procurement\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Modules\Procurement\Models\Bill;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\Quotation;
use App\Modules\Procurement\Models\SupplierContact;
use App\Modules\Procurement\Services\ProcurementDocumentService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The read-only vendor portal: a supplier contact sees only their own
 * supplier's purchase orders, quotations, and bills. Every query is scoped to
 * the signed-in contact's supplier AND organization, so no vendor can ever see
 * another vendor's — or another tenant's — documents.
 */
class PortalController extends Controller
{
    /** Document type segment → model, for the scoped PDF endpoint. */
    private const TYPES = [
        'purchase-orders' => PurchaseOrder::class,
        'quotations' => Quotation::class,
        'bills' => Bill::class,
    ];

    public function dashboard(Request $request): View
    {
        $contact = $this->contact($request);
        [$orgId, $supplierId] = [$contact->organization_id, $contact->procurement_supplier_id];

        $scope = fn (string $class) => $class::where('organization_id', $orgId)
            ->where('procurement_supplier_id', $supplierId)
            ->latest('id')->limit(100)->get();

        return view('vendor.dashboard', [
            'contact' => $contact,
            'supplier' => $contact->supplier,
            'orders' => $scope(PurchaseOrder::class),
            'quotations' => $scope(Quotation::class),
            'bills' => $scope(Bill::class),
        ]);
    }

    /** Stream a PDF for one of the vendor's own documents (scoped lookup). */
    public function pdf(Request $request, string $type, int $id, ProcurementDocumentService $docs)
    {
        $contact = $this->contact($request);
        $class = self::TYPES[$type] ?? abort(404);

        $model = $class::where('organization_id', $contact->organization_id)
            ->where('procurement_supplier_id', $contact->procurement_supplier_id)
            ->findOrFail($id);

        return response($docs->pdf($model), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$docs->filename($model).'"',
        ]);
    }

    private function contact(Request $request): SupplierContact
    {
        /** @var SupplierContact $contact */
        $contact = $request->attributes->get('vendorContact');

        return $contact;
    }
}
