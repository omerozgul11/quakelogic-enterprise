<?php

namespace App\Modules\Procurement\Models\Concerns;

use App\Modules\Procurement\Models\Approval;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Gives a procurement document (PR / PO / Bill payment) a running approval chain.
 * A document has at most one active chain at a time; `latestApproval()` returns
 * the most recent one (its status drives whether the document is approved).
 */
trait HasApprovals
{
    public function approvals(): MorphMany
    {
        return $this->morphMany(Approval::class, 'approvable')->latest('id');
    }

    /** The most recent approval chain for this document (with its steps), or null. */
    public function latestApproval(): ?Approval
    {
        return $this->approvals()->with(['steps.approver:id,name', 'steps.decider:id,name', 'flow:id,name'])->first();
    }
}
