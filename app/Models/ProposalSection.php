<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProposalSection extends Model
{
    protected $fillable = [
        'organization_id', 'proposal_submission_id', 'section_key', 'heading', 'content', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(ProposalSubmission::class, 'proposal_submission_id');
    }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }
}
