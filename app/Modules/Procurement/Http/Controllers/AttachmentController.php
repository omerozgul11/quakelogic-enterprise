<?php

namespace App\Modules\Procurement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Procurement\Models\Bill;
use App\Modules\Procurement\Models\ProcurementAttachment;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\PurchaseRequest;
use App\Modules\Procurement\Models\Quotation;
use App\Modules\Procurement\Models\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * File attachments for procurement documents. One controller serves all four
 * document types (PR / Quotation / PO / Bill) via a small entity map. Files go
 * to the private `local` disk; downloads run through an authorized action that
 * re-checks the parent document's policy and org scope.
 */
class AttachmentController extends Controller
{
    /** Route segment → model. Keeps the polymorphic parent to a safe whitelist. */
    private const ENTITIES = [
        'purchase-requests' => PurchaseRequest::class,
        'quotations' => Quotation::class,
        'purchase-orders' => PurchaseOrder::class,
        'bills' => Bill::class,
        'suppliers' => Supplier::class,
    ];

    private const MIMES = 'application/pdf,image/jpeg,image/png,image/heic,image/heif,'
        .'application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,'
        .'application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,'
        .'text/plain,text/csv';

    public function store(Request $request, string $entity, int $id): RedirectResponse
    {
        $model = $this->resolve($entity, $id, $request->user()->organization_id);
        $this->authorize('update', $model);

        $request->validate([
            'file' => ['required', 'file', 'max:25600', 'mimetypes:'.self::MIMES],
        ]);

        $file = $request->file('file');
        $stored = (string) Str::ulid().'.'.($file->getClientOriginalExtension() ?: 'bin');
        $path = $file->storeAs("procurement/attachments/{$entity}/{$id}", $stored, 'local');

        $model->attachments()->create([
            'organization_id' => $model->organization_id,
            'uploaded_by' => $request->user()->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        return back()->with('success', 'Attachment uploaded.');
    }

    public function download(Request $request, ProcurementAttachment $attachment): mixed
    {
        $parent = $attachment->attachable;
        abort_if($parent === null, 404);
        $this->authorize('view', $parent);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    public function destroy(Request $request, ProcurementAttachment $attachment): RedirectResponse
    {
        $parent = $attachment->attachable;
        abort_if($parent === null, 404);
        $this->authorize('update', $parent);

        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();

        return back()->with('success', 'Attachment removed.');
    }

    private function resolve(string $entity, int $id, int $orgId): Model
    {
        $class = self::ENTITIES[$entity] ?? abort(404);

        return $class::where('organization_id', $orgId)->findOrFail($id);
    }

    /**
     * Serialize a document's attachments for its Inertia Show page. Expects the
     * `attachments.uploader` relation to be eager-loaded.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function serialize(Model $model): array
    {
        return $model->attachments->map(fn (ProcurementAttachment $a) => [
            'id' => $a->id,
            'name' => $a->original_name,
            'size' => $a->size,
            'mime' => $a->mime,
            'uploaded_by' => $a->uploader?->name,
            'created_at' => $a->created_at?->toDateString(),
            'download_url' => route('procurement.attachments.download', $a),
        ])->all();
    }
}
