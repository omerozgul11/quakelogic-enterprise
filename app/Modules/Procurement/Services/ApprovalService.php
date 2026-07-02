<?php

namespace App\Modules\Procurement\Services;

use App\Models\User;
use App\Modules\Procurement\Enums\ApprovalDocumentType;
use App\Modules\Procurement\Enums\ApprovalStatus;
use App\Modules\Procurement\Enums\PurchaseOrderStatus;
use App\Modules\Procurement\Models\Approval;
use App\Modules\Procurement\Models\ApprovalFlow;
use App\Modules\Procurement\Models\ApprovalStep;
use App\Modules\Procurement\Models\BillPayment;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\PurchaseRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The multi-level approval chain engine (ported from the legacy RISE Purchase
 * plugin). A document is submitted → the matching amount-tiered flow is
 * instantiated into a per-document chain → approvers act step by step (an
 * optional digital signature per step); a reject ends the chain, all-approved
 * completes it and advances the underlying document.
 *
 * Backward-compatible: when no flow is configured for a document type, start()
 * returns null and callers fall back to their existing simple approve/reject.
 */
class ApprovalService
{
    /** The active flow governing this document, or null (highest matching tier). */
    public function flowFor(Model $doc): ?ApprovalFlow
    {
        $type = ApprovalDocumentType::forModel($doc);

        return ApprovalFlow::where('organization_id', $doc->organization_id)
            ->where('document_type', $type->value)
            ->where('is_active', true)
            ->where('min_amount', '<=', $this->amountOf($doc))
            ->with('steps')
            ->orderByDesc('min_amount')->orderByDesc('id')
            ->get()
            ->first(fn (ApprovalFlow $f) => $f->steps->isNotEmpty());
    }

    public function hasFlow(Model $doc): bool
    {
        return $this->flowFor($doc) !== null;
    }

    /**
     * Instantiate a chain for the document from the matching flow, superseding
     * any prior open chain. Returns null when no flow applies.
     */
    public function start(Model $doc, int $actorId): ?Approval
    {
        $flow = $this->flowFor($doc);
        if (! $flow) {
            return null;
        }

        return DB::transaction(function () use ($doc, $flow, $actorId) {
            Approval::where('approvable_type', $doc::class)
                ->where('approvable_id', $doc->getKey())
                ->where('status', ApprovalStatus::Pending)
                ->update(['status' => ApprovalStatus::Rejected, 'completed_at' => now()]);

            $approval = Approval::create([
                'organization_id' => $doc->organization_id,
                'approvable_type' => $doc::class,
                'approvable_id' => $doc->getKey(),
                'procurement_approval_flow_id' => $flow->id,
                'status' => ApprovalStatus::Pending,
                'submitted_by' => $actorId,
                'submitted_at' => now(),
            ]);

            foreach ($flow->steps as $step) {
                $approval->steps()->create([
                    'organization_id' => $doc->organization_id,
                    'position' => $step->position,
                    'name' => $step->name,
                    'approver_type' => $step->approver_type,
                    'approver_user_id' => $step->approver_user_id,
                    'approver_role' => $step->approver_role,
                    'require_signature' => $step->require_signature,
                    'status' => ApprovalStatus::Pending,
                ]);
            }

            return $approval->load('steps');
        });
    }

    /**
     * Approve the current step for $user, capturing a signature (base64 PNG) when
     * provided/required. Completes the chain (and advances the document) once the
     * last step is approved.
     */
    public function approveStep(Approval $approval, User $user, ?string $signatureBase64 = null, ?string $note = null): Approval
    {
        return DB::transaction(function () use ($approval, $user, $signatureBase64, $note) {
            $step = $this->guardActable($approval, $user);

            if ($step->require_signature && ! $signatureBase64) {
                throw new RuntimeException('A signature is required to approve this step.');
            }

            $attrs = [
                'status' => ApprovalStatus::Approved,
                'decided_by' => $user->id,
                'decided_at' => now(),
                'note' => $note,
            ];
            if ($signatureBase64) {
                $attrs['signature_path'] = $this->storeSignature($approval, $step, $signatureBase64);
            }
            $step->forceFill($attrs)->save();

            $approval->load('steps');
            if (! $approval->currentStep()) {
                $approval->forceFill(['status' => ApprovalStatus::Approved, 'completed_at' => now()])->save();
                $this->onApproved($approval, $user);
            }

            return $approval->load('steps');
        });
    }

    /** Reject the current step for $user — ends the whole chain. */
    public function rejectStep(Approval $approval, User $user, ?string $note = null): Approval
    {
        return DB::transaction(function () use ($approval, $user, $note) {
            $step = $this->guardActable($approval, $user);

            $step->forceFill([
                'status' => ApprovalStatus::Rejected,
                'decided_by' => $user->id,
                'decided_at' => now(),
                'note' => $note,
            ])->save();

            $approval->forceFill(['status' => ApprovalStatus::Rejected, 'completed_at' => now()])->save();
            $this->onRejected($approval, $user, $note);

            return $approval->load('steps');
        });
    }

    /** The current pending step, asserting the approval is open and $user may act. */
    private function guardActable(Approval $approval, User $user): ApprovalStep
    {
        $approval->load('steps');

        if ($approval->status !== ApprovalStatus::Pending) {
            throw new RuntimeException('This approval is already '.$approval->status->value.'.');
        }
        $step = $approval->currentStep();
        if (! $step) {
            throw new RuntimeException('There is no pending step to act on.');
        }
        if (! $step->isEligible($user)) {
            throw new RuntimeException('You are not an assigned approver for the current step.');
        }

        return $step;
    }

    /** Advance the underlying document once its chain is fully approved. */
    private function onApproved(Approval $approval, User $actor): void
    {
        // Explicit query (not lazy access) — lazy loading is disabled app-wide.
        $doc = $approval->approvable()->first();
        if (! $doc) {
            return;
        }

        match ($doc::class) {
            PurchaseRequest::class => app(PurchaseRequestService::class)->approve($doc, $actor->id),
            PurchaseOrder::class => app(PurchaseOrderService::class)->approve($doc, $actor->id),
            BillPayment::class => app(BillService::class)->approvePayment($doc, $actor->id),
            default => null,
        };
    }

    /** Reflect a chain rejection onto the underlying document. */
    private function onRejected(Approval $approval, User $actor, ?string $note): void
    {
        $doc = $approval->approvable()->first();
        if (! $doc) {
            return;
        }

        match ($doc::class) {
            PurchaseRequest::class => app(PurchaseRequestService::class)->reject($doc, $note, $actor->id),
            // PO has no Rejected state — return it to Draft so it can be revised and resubmitted.
            PurchaseOrder::class => $doc->forceFill(['status' => PurchaseOrderStatus::Draft])->save(),
            BillPayment::class => app(BillService::class)->rejectPayment($doc, $actor->id),
            default => null,
        };
    }

    private function amountOf(Model $doc): float
    {
        return (float) ($doc instanceof BillPayment ? $doc->amount : $doc->total);
    }

    /** Persist a signature (base64 PNG, with or without a data-URL prefix) privately. */
    private function storeSignature(Approval $approval, ApprovalStep $step, string $base64): string
    {
        if (str_contains($base64, ',')) {
            $base64 = substr($base64, strpos($base64, ',') + 1);
        }
        $binary = base64_decode($base64, true);
        if ($binary === false || $binary === '') {
            throw new RuntimeException('The signature image could not be read.');
        }

        $path = "procurement/signatures/{$approval->id}/step-{$step->id}-".Str::ulid().'.png';
        Storage::disk('local')->put($path, $binary);

        return $path;
    }
}
