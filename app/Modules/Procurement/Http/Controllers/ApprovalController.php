<?php

namespace App\Modules\Procurement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Procurement\Enums\ApprovalStatus;
use App\Modules\Procurement\Models\Approval;
use App\Modules\Procurement\Models\ApprovalStep;
use App\Modules\Procurement\Models\BillPayment;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\PurchaseRequest;
use App\Modules\Procurement\Services\ApprovalService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Decisions on a running approval chain (approve/reject the current step, with an
 * optional signature) and serving the captured signature images. One controller
 * serves all three approvable document types via a whitelisted entity map.
 */
class ApprovalController extends Controller
{
    private const ENTITIES = [
        'purchase-requests' => PurchaseRequest::class,
        'purchase-orders' => PurchaseOrder::class,
        'bill-payments' => BillPayment::class,
    ];

    public function __construct(private readonly ApprovalService $approvals) {}

    public function approve(Request $request, string $entity, int $id): RedirectResponse
    {
        $approval = $this->openApproval($this->resolve($entity, $id, $request->user()->organization_id));
        $data = $request->validate([
            'signature' => ['nullable', 'string'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->approvals->approveStep($approval, $request->user(), $data['signature'] ?? null, $data['note'] ?? null);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Approval recorded.');
    }

    public function reject(Request $request, string $entity, int $id): RedirectResponse
    {
        $approval = $this->openApproval($this->resolve($entity, $id, $request->user()->organization_id));
        $data = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);

        try {
            $this->approvals->rejectStep($approval, $request->user(), $data['note'] ?? null);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Rejection recorded.');
    }

    /** Serve a step's captured signature image (authorized to the org). */
    public function signature(Request $request, ApprovalStep $step)
    {
        abort_if($step->organization_id !== $request->user()->organization_id, 404);
        abort_if(! $step->signature_path || ! Storage::disk('local')->exists($step->signature_path), 404);

        return response(Storage::disk('local')->get($step->signature_path), 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'inline; filename="signature.png"',
        ]);
    }

    private function resolve(string $entity, int $id, int $orgId): Model
    {
        $class = self::ENTITIES[$entity] ?? abort(404);

        return $class::where('organization_id', $orgId)->findOrFail($id);
    }

    private function openApproval(Model $doc): Approval
    {
        $approval = $doc->latestApproval();
        abort_if(! $approval || $approval->status !== ApprovalStatus::Pending, 404);

        return $approval;
    }

    /**
     * Serialize a document's current approval chain for its Show page, including
     * whether $user may act on the current step. Null when there is no chain.
     *
     * @return array<string,mixed>|null
     */
    public static function serialize(?Approval $approval, User $user): ?array
    {
        if (! $approval) {
            return null;
        }

        $current = $approval->currentStep();

        return [
            'id' => $approval->id,
            'status' => $approval->status->value,
            'status_label' => $approval->status->label(),
            'status_color' => $approval->status->color(),
            'flow' => $approval->flow?->name,
            'submitted_at' => $approval->submitted_at?->toIso8601String(),
            'can_act' => $approval->status === ApprovalStatus::Pending && $current !== null && $current->isEligible($user),
            'steps' => $approval->steps->map(fn (ApprovalStep $s) => [
                'id' => $s->id,
                'position' => $s->position,
                'name' => $s->name,
                'approver' => $s->approver_type === 'user'
                    ? ($s->approver?->name ?? 'User #'.$s->approver_user_id)
                    : 'Anyone with role: '.$s->approver_role,
                'require_signature' => $s->require_signature,
                'status' => $s->status->value,
                'status_label' => $s->status->label(),
                'status_color' => $s->status->color(),
                'decided_by' => $s->decider?->name,
                'decided_at' => $s->decided_at?->toIso8601String(),
                'note' => $s->note,
                'is_current' => $current !== null && $s->id === $current->id,
                'signature_url' => $s->signature_path ? route('procurement.approvals.signature', $s) : null,
            ])->all(),
        ];
    }
}
