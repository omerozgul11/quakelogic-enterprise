<?php

namespace App\Modules\Procurement\Models\Concerns;

use App\Modules\Procurement\Models\ProcurementAttachment;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Gives a procurement document (PR / Quotation / PO / Bill) a polymorphic set
 * of file attachments.
 */
trait HasProcurementAttachments
{
    public function attachments(): MorphMany
    {
        return $this->morphMany(ProcurementAttachment::class, 'attachable')->latest('id');
    }
}
